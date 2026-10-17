<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Service\BildQuellenService;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BildQuellenServiceTest extends FunctionalTestCase
{
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conrat/crgalerie'];

    protected array $configurationToUseInTestInstance = [
        'GFX' => [
            'processor' => 'ImageMagick',
            'processor_path' => '/usr/bin/',
            'processor_enabled' => true,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Modul.csv');
    }

    private function datei(int $breite, int $hoehe, string $name): File
    {
        $verzeichnis = Environment::getVarPath() . '/transient/quellen/';
        @mkdir($verzeichnis, 0777, true);
        $pfad = $this->erzeugeTestbild($verzeichnis . $name, $breite, $hoehe);
        $speicher = $this->get(StorageRepository::class)->findByUid(1);
        self::assertNotNull($speicher);
        $ordner = $speicher->hasFolder('/quellen/') ? $speicher->getFolder('/quellen/') : $speicher->createFolder('/quellen/');
        $datei = $ordner->addFile($pfad, $name);
        self::assertInstanceOf(File::class, $datei);
        return $datei;
    }

    #[Test]
    public function grossesBildErgibtDreiBreitenUndBegrenzteGrossfassung(): void
    {
        $quellen = $this->get(BildQuellenService::class)->fuer($this->datei(3000, 2000, 'gross.jpg'));
        self::assertNotNull($quellen);
        self::assertSame(3, substr_count($quellen->srcset, 'w,') + 1);
        self::assertStringContainsString(' 400w', $quellen->srcset);
        self::assertStringContainsString(' 800w', $quellen->srcset);
        self::assertStringContainsString(' 1200w', $quellen->srcset);
        self::assertSame([800, 533], [$quellen->breite, $quellen->hoehe]);
        self::assertSame([2560, 1707], [$quellen->grossBreite, $quellen->grossHoehe]);
        self::assertSame('1.5000', $quellen->verhaeltnis);
    }

    #[Test]
    public function kleinesBildWirdNichtVergroessert(): void
    {
        $quellen = $this->get(BildQuellenService::class)->fuer($this->datei(500, 700, 'klein.jpg'));
        self::assertNotNull($quellen);
        self::assertStringContainsString(' 400w', $quellen->srcset);
        self::assertStringContainsString(' 500w', $quellen->srcset);
        self::assertStringNotContainsString('1200w', $quellen->srcset);
        self::assertSame([500, 700], [$quellen->grossBreite, $quellen->grossHoehe]);
        self::assertSame('0.7143', $quellen->verhaeltnis);
    }

    #[Test]
    public function fehlendeDateiErgibtNull(): void
    {
        $datei = $this->datei(100, 100, 'weg.jpg');
        unlink(Environment::getPublicPath() . '/fileadmin/quellen/weg.jpg');
        $this->getConnectionPool()->getConnectionForTable('sys_file')->update('sys_file', ['missing' => 1], ['uid' => $datei->getUid()]);
        $frisch = $this->get(\TYPO3\CMS\Core\Resource\ResourceFactory::class)->getFileObject($datei->getUid());
        self::assertNull($this->get(BildQuellenService::class)->fuer($frisch));
    }

    #[Test]
    public function fehlendeMasseErgebenFallbackVerhaeltnis(): void
    {
        $datei = $this->datei(300, 200, 'ohnemass.jpg');
        $datei->updateProperties(['width' => 0, 'height' => 0]);
        self::assertSame('1.5000', $this->get(BildQuellenService::class)->fuer($datei)?->verhaeltnis);
    }

    #[Test]
    public function zweiBreitenErgebenZweiEintraege(): void
    {
        $quellen = $this->get(BildQuellenService::class)->fuer($this->datei(3000, 2000, 'zwei.jpg'), [800, 400]);
        self::assertNotNull($quellen);
        self::assertSame(2, substr_count($quellen->srcset, 'w,') + 1);
        self::assertMatchesRegularExpression('/ 400w, .* 800w$/', $quellen->srcset);
        self::assertSame(800, $quellen->breite);
    }

    #[Test]
    public function leereBreitenNutzenStandard(): void
    {
        $quellen = $this->get(BildQuellenService::class)->fuer($this->datei(3000, 2000, 'leer.jpg'), []);
        self::assertStringContainsString(' 1200w', (string)$quellen?->srcset);
    }

    #[Test]
    public function extbaseFileReferenceWirdUnterstuetzt(): void
    {
        $datei = $this->datei(1000, 500, 'referenz.jpg');
        $kern = new \TYPO3\CMS\Core\Resource\FileReference(['uid_local' => $datei->getUid(), 'uid' => 1]);
        $referenz = new \TYPO3\CMS\Extbase\Domain\Model\FileReference();
        $referenz->setOriginalResource($kern);
        $quellen = $this->get(BildQuellenService::class)->fuer($referenz);
        self::assertNotNull($quellen);
        self::assertSame('2.0000', $quellen->verhaeltnis);
    }
    /**
     * @return list<string> Verarbeitungsanweisungen (maxWidth/maxHeight) der gespeicherten Fassungen
     */
    private function fassungen(File $datei): array
    {
        $zeilen = $this->getConnectionPool()->getConnectionForTable('sys_file_processedfile')
            ->select(['configuration'], 'sys_file_processedfile', ['original' => $datei->getUid()])->fetchFirstColumn();
        $ergebnis = [];
        foreach ($zeilen as $konfiguration) {
            $werte = unserialize((string)$konfiguration, ['allowed_classes' => false]);
            $ergebnis[] = (string)($werte['maxWidth'] ?? '') . 'x' . (string)($werte['maxHeight'] ?? '');
        }
        sort($ergebnis);
        return $ergebnis;
    }

    #[Test]
    public function grossNullErzeugtKeineLightboxFassung(): void
    {
        $datei = $this->datei(3000, 2000, 'ohnegross.jpg');
        $quellen = $this->get(BildQuellenService::class)->fuer($datei, [400, 800], 0);
        self::assertNotNull($quellen);
        self::assertSame($quellen->src, $quellen->grossUrl);
        self::assertSame([800, 533], [$quellen->grossBreite, $quellen->grossHoehe]);
        self::assertSame(['400x', '800x'], $this->fassungen($datei));
    }

    #[Test]
    public function standardErzeugtVorschauUndLightboxFassung(): void
    {
        $datei = $this->datei(3000, 2000, 'alle.jpg');
        self::assertNotNull($this->get(BildQuellenService::class)->fuer($datei));
        self::assertSame(['1200x', '2560x2560', '400x', '800x'], $this->fassungen($datei));
    }
}
