<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Domain\Dto\BildAnzeige;
use Conrat\Crgalerie\Domain\Repository\BilderRepository;
use Conrat\Crgalerie\Domain\Repository\GalerieRepository;
use Conrat\Crgalerie\Domain\Sortierung;
use Conrat\Crgalerie\Service\BildAnzeigeService;
use Conrat\Crgalerie\Tests\Functional\FrontendKontextTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BildAnzeigeServiceTest extends FunctionalTestCase
{
    use FrontendKontextTrait;

    protected array $coreExtensionsToLoad = ['filemetadata'];
    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];
    protected array $pathsToProvideInTestInstance = [
        'typo3conf/ext/crgalerie/Tests/Functional/Fixtures/Files/' => 'fileadmin/galerie/',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Galerien.csv');
        $this->richteFrontendKontextEin();
    }

    /**
     * @return array<string, BildAnzeige> nach Titel
     */
    private function anzeigen(string $creditFeld = '', string $lizenzFeld = '', string $standardUrheber = ''): array
    {
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(1);
        self::assertNotNull($galerie);
        $bilder = $this->get(BilderRepository::class)->findByGalerie($galerie, Sortierung::ManuellAufsteigend);
        $ergebnis = [];
        foreach ($this->get(BildAnzeigeService::class)->fuerBilder($bilder, $creditFeld, $lizenzFeld, $standardUrheber) as $anzeige) {
            $ergebnis[$anzeige->titel] = $anzeige;
        }
        return $ergebnis;
    }

    #[Test]
    public function bildMitFehlenderDateiWirdUebersprungen(): void
    {
        self::assertSame(['Burgtor', 'Beta', 'Zelt'], array_keys($this->anzeigen()));
    }

    #[Test]
    public function metadatenHabenVorrangVorDatensatz(): void
    {
        $bild = $this->anzeigen()['Burgtor'];
        self::assertSame('Das Tor im Abendlicht', $bild->beschreibung);
    }

    #[Test]
    public function copyrightAusBildunterschriftWirdErkanntUndEntfernt(): void
    {
        $bild = $this->anzeigen()['Zelt'];
        self::assertSame('Aufbau am Morgen', $bild->beschreibung);
        self::assertSame('© Max Muster', $bild->credit);
    }

    #[Test]
    public function konfigurierteFelderErgebenCreditMitLizenz(): void
    {
        $bild = $this->anzeigen('creator', 'copyright')['Burgtor'];
        self::assertSame('CC BY 4.0 © Anna Beispiel', $bild->credit);
    }

    #[Test]
    public function ohneKonfigurationKeinCreditAusMetadaten(): void
    {
        self::assertSame('', $this->anzeigen()['Burgtor']->credit);
    }

    #[Test]
    public function nichtExistierendeSpalteFuehrtNichtZuFehler(): void
    {
        $bild = $this->anzeigen('urheber', 'cclicense')['Burgtor'];
        self::assertSame('', $bild->credit);
    }

    #[Test]
    public function ohneUrheberGiltDerFotografDerGalerie(): void
    {
        self::assertSame('© Galerie-Fotograf', $this->anzeigen('', '', 'Galerie-Fotograf')['Beta']->credit);
    }

    #[Test]
    public function urheberAusTextUndMetadatenGewinnenGegenFotograf(): void
    {
        $bilder = $this->anzeigen('creator', 'copyright', 'Galerie-Fotograf');
        self::assertSame('© Max Muster', $bilder['Zelt']->credit);
        self::assertSame('CC BY 4.0 © Anna Beispiel', $bilder['Burgtor']->credit);
    }

    #[Test]
    public function uebersprungenesBildWirdEinmalMitUidProtokolliert(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $meldungen = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->meldungen[] = $level . ': ' . $message;
            }
        };
        $service = new BildAnzeigeService($logger, $this->get(CharsetConverter::class));
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(1);
        self::assertNotNull($galerie);
        $bilder = $this->get(BilderRepository::class)->findByGalerie($galerie, Sortierung::ManuellAufsteigend);
        $service->fuerBilder($bilder);
        $service->fuerBilder($bilder);

        self::assertCount(1, $logger->meldungen);
        self::assertStringStartsWith('warning: ', $logger->meldungen[0]);
        self::assertStringContainsString('Bild 6 ', $logger->meldungen[0]);
    }

    /**
     * Legt (wie der Upload) eine Datei unter $dateiname an, hängt sie an Bild 2 („Alpha“) und setzt dessen Titel.
     */
    private function anzeigeMitDatei(string $dateiname, string $titel): BildAnzeige
    {
        copy($this->instancePath . '/fileadmin/galerie/quer1.jpg', $this->instancePath . '/fileadmin/galerie/' . $dateiname);
        $verbindung = $this->getConnectionPool();
        $verbindung->getConnectionForTable('sys_file')->insert('sys_file', [
            'uid' => 50, 'pid' => 0, 'storage' => 1, 'type' => 2,
            'identifier' => '/galerie/' . $dateiname, 'identifier_hash' => sha1('/galerie/' . $dateiname), 'folder_hash' => 'f1',
            'name' => $dateiname, 'extension' => 'jpg', 'mime_type' => 'image/jpeg', 'size' => 100, 'missing' => 0,
        ]);
        $verbindung->getConnectionForTable('sys_file_metadata')->insert('sys_file_metadata', [
            'uid' => 50, 'pid' => 0, 'file' => 50, 'sys_language_uid' => 0, 'width' => 60, 'height' => 40,
        ]);
        $verbindung->getConnectionForTable('sys_file_reference')->update('sys_file_reference', ['uid_local' => 50], ['uid' => 2]);
        $verbindung->getConnectionForTable('tx_crgalerie_domain_model_bilder')->update('tx_crgalerie_domain_model_bilder', ['titel' => $titel], ['uid' => 2]);

        foreach ($this->anzeigenListe() as $anzeige) {
            if ($anzeige->datei->getOriginalResource()->getOriginalFile()->getName() === $dateiname) {
                return $anzeige;
            }
        }
        self::fail('Bild mit Datei ' . $dateiname . ' nicht gefunden');
    }

    /**
     * @return list<BildAnzeige>
     */
    private function anzeigenListe(): array
    {
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(1);
        self::assertNotNull($galerie);
        $bilder = $this->get(BilderRepository::class)->findByGalerie($galerie, Sortierung::ManuellAufsteigend);
        return $this->get(BildAnzeigeService::class)->fuerBilder($bilder);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function dateinamenTitel(): array
    {
        return [
            'Upload-Titel aus Dateiname' => ['bu_0010.jpg', 'bu 0010'],
            'Dateiname ohne Endung' => ['IMG_1234.jpg', 'IMG_1234'],
            'Großschreibung egal' => ['IMG_1234.jpg', 'img 1234'],
            'Dublette mit _01' => ['bu_0010_01.jpg', 'bu 0010'],
            'Umlaute im Originalnamen' => ['Boese-Hexe.jpg', 'Böse Hexe'],
            'ä am Wortanfang' => ['AErger-im-Hof.jpg', 'Ärger im Hof'],
            'ß' => ['Strasse.jpg', 'Straße'],
            'nicht-lateinische Schrift' => ['日本.jpg', '日本'],
        ];
    }

    #[Test]
    #[DataProvider('dateinamenTitel')]
    public function titelAusDateinameGiltAlsLeer(string $dateiname, string $titel): void
    {
        $anzeige = $this->anzeigeMitDatei($dateiname, $titel);
        self::assertSame('', $anzeige->titel);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function echteTitel(): array
    {
        return [
            'Satz' => ['bu_0010.jpg', 'Burghof im Winter'],
            'Dateiname nur als Anfang' => ['bu_0010.jpg', 'bu 0010 Burghof'],
            'nur Symbol' => ['bild.jpg', '★'],
            'nicht-lateinisch, andere Datei' => ['bild.jpg', '日本'],
            'Symbol gegen Symbol-Datei' => ['★.jpg', '★'],
        ];
    }

    #[Test]
    #[DataProvider('echteTitel')]
    public function echteTitelBleibenSichtbar(string $dateiname, string $titel): void
    {
        self::assertSame($titel, $this->anzeigeMitDatei($dateiname, $titel)->titel);
    }

    #[Test]
    public function echterTitelBleibtErhalten(): void
    {
        $anzeige = $this->anzeigeMitDatei('bu_0010.jpg', 'Burghof im Winter');
        self::assertSame('Burghof im Winter', $anzeige->titel);
    }

    #[Test]
    public function gespeicherterTitelWirdNichtVeraendert(): void
    {
        $this->anzeigeMitDatei('bu_0010.jpg', 'bu 0010');
        $titel = $this->getConnectionPool()->getConnectionForTable('tx_crgalerie_domain_model_bilder')
            ->select(['titel'], 'tx_crgalerie_domain_model_bilder', ['uid' => 2])->fetchOne();
        self::assertSame('bu 0010', $titel);
    }
}
