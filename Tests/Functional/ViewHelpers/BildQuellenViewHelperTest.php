<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\ViewHelpers;

use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BildQuellenViewHelperTest extends FunctionalTestCase
{
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];

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

    private function datei(string $name): File
    {
        $verzeichnis = Environment::getVarPath() . '/transient/vh/';
        @mkdir($verzeichnis, 0777, true);
        $pfad = $this->erzeugeTestbild($verzeichnis . $name, 1000, 500);
        $speicher = $this->get(StorageRepository::class)->findByUid(1);
        self::assertNotNull($speicher);
        $ordner = $speicher->hasFolder('/vh/') ? $speicher->getFolder('/vh/') : $speicher->createFolder('/vh/');
        $datei = $ordner->addFile($pfad, $name);
        self::assertInstanceOf(File::class, $datei);
        return $datei;
    }

    /**
     * @param array<string, mixed> $variablen
     */
    private function rendere(string $vorlage, array $variablen): string
    {
        $verzeichnis = Environment::getVarPath() . '/transient/vh/';
        @mkdir($verzeichnis, 0777, true);
        $pfad = $verzeichnis . 'vorlage-' . md5($vorlage) . '.html';
        file_put_contents($pfad, '{namespace crg=Conrat\\Crgalerie\\ViewHelpers}' . $vorlage);
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(templatePathAndFilename: $pfad));
        $view->assignMultiple($variablen);
        return trim($view->render());
    }

    #[Test]
    public function gibtQuellenImInhaltAus(): void
    {
        $ausgabe = $this->rendere('<crg:bildQuellen datei="{d}" as="quellen">{quellen.src}|{quellen.verhaeltnis}</crg:bildQuellen>', ['d' => $this->datei('a.jpg')]);
        self::assertStringContainsString('fileadmin/', $ausgabe);
        self::assertStringEndsWith('|2.0000', $ausgabe);
    }

    #[Test]
    public function verschachtelungBehaeltAeussereVariable(): void
    {
        $a = $this->datei('aussen.jpg');
        $b = $this->datei('innen.jpg');
        $ausgabe = $this->rendere(
            '<crg:bildQuellen datei="{a}" as="q">[{q.src}]<crg:bildQuellen datei="{b}" as="q">(<f:if condition="{q.src}">i</f:if>)</crg:bildQuellen>[{q.src}]</crg:bildQuellen>',
            ['a' => $a, 'b' => $b],
        );
        self::assertSame(2, preg_match_all('/\[(fileadmin\/[^\]]*aussen[^\]]*)\]/', $ausgabe, $treffer));
        self::assertSame($treffer[1][0], $treffer[1][1]);
        self::assertStringContainsString('(i)', $ausgabe);
    }

    #[Test]
    public function fehlendeDateiRendertNichts(): void
    {
        self::assertSame('', $this->rendere('<crg:bildQuellen datei="{d}" as="q">X</crg:bildQuellen>', ['d' => null]));
    }
    #[Test]
    public function grossNullNutztVorschauAlsGrosseFassung(): void
    {
        $ausgabe = $this->rendere(
            '<crg:bildQuellen datei="{d}" breiten="{0: 400, 1: 800}" gross="0" as="q">{q.src}|{q.grossUrl}|{q.breite}|{q.grossBreite}</crg:bildQuellen>',
            ['d' => $this->datei('ohnegross.jpg')],
        );
        [$src, $gross, $breite, $grossBreite] = explode('|', $ausgabe);
        self::assertStringContainsString('_processed_', $src);
        self::assertSame($src, $gross);
        self::assertSame('800', $breite);
        self::assertSame($breite, $grossBreite);
    }
}
