<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Service\GalerieBearbeitung;
use Conrat\Crgalerie\Configuration\ExtensionKonfiguration;
use Conrat\Crgalerie\Service\GalerieBearbeitungFehler;
use Conrat\Crgalerie\Service\GalerieDateiablage;
use Conrat\Crgalerie\Service\LoeschErgebnis;
use Conrat\Crgalerie\Service\ModulAbfragen;
use Conrat\Crgalerie\Tests\Functional\BackendBenutzerTrait;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class GalerieBearbeitungTest extends FunctionalTestCase
{
    use BackendBenutzerTrait;
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Modul.csv');
        $ordner = Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/';
        @mkdir($ordner, 0777, true);
        foreach (['eins', 'zwei', 'drei'] as $name) {
            $this->erzeugeTestbild($ordner . $name . '.jpg', 60, 40);
        }
    }

    /**
     * Liest ohne Restriktionen: Connection::select() blendet versteckte und gelöschte Datensätze aus.
     */
    private function feld(string $tabelle, int $uid, string $feld): mixed
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($tabelle);
        $queryBuilder->getRestrictions()->removeAll();
        $zeile = $queryBuilder->select($feld)->from($tabelle)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()->fetchAssociative();
        return $zeile[$feld] ?? null;
    }

    #[Test]
    public function bildAnlegenHaengtHintenAnUndVerknuepftDatei(): void
    {
        $this->meldeAn(1);
        $uid = $this->get(GalerieBearbeitung::class)->bildAnlegen(1, 0, 2, 'Neues Bild');
        self::assertGreaterThan(3, $uid);
        self::assertSame(4, (int)$this->feld('tx_crgalerie_domain_model_bilder', $uid, 'sortierung'));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', $uid, 'galerie'));
        $letztes = $this->get(ModulAbfragen::class)->bilder(1)[3];
        self::assertSame($uid, $letztes['uid']);
        self::assertSame(2, $letztes['datei']);
    }

    #[Test]
    public function bilderSortierenIgnoriertFremdeUids(): void
    {
        $this->meldeAn(1);
        $this->get(GalerieBearbeitung::class)->bilderSortieren(1, [3, 999, 1, 2]);
        self::assertSame([3, 1, 2], array_column($this->get(ModulAbfragen::class)->bilder(1), 'uid'));
    }

    #[Test]
    public function galerienSortierenInZehnerschritten(): void
    {
        $this->meldeAn(1);
        $this->get(GalerieBearbeitung::class)->galerienSortieren([2, 1]);
        self::assertSame(10, (int)$this->feld('tx_crgalerie_domain_model_galerie', 2, 'sortierung'));
        self::assertSame(20, (int)$this->feld('tx_crgalerie_domain_model_galerie', 1, 'sortierung'));
    }

    #[Test]
    public function sichtbarkeitUmschalten(): void
    {
        $this->meldeAn(1);
        $bearbeitung = $this->get(GalerieBearbeitung::class);
        $bearbeitung->bildSichtbarkeit(2, true);
        $bearbeitung->galerieSichtbarkeit(1, false);
        self::assertSame(0, (int)$this->feld('tx_crgalerie_domain_model_bilder', 2, 'hidden'));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_galerie', 1, 'hidden'));
    }

    #[Test]
    public function bildLoeschenEntferntDatensatzUndDatei(): void
    {
        $this->meldeAn(1);
        self::assertSame(LoeschErgebnis::DateiGeloescht, $this->get(GalerieBearbeitung::class)->bildLoeschen(1));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', 1, 'deleted'));
        self::assertFileDoesNotExist(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/eins.jpg');
    }

    #[Test]
    public function bildLoeschenBehaeltDateiBeiFremdreferenz(): void
    {
        $this->meldeAn(1);
        self::assertSame(LoeschErgebnis::DateiWirdVerwendet, $this->get(GalerieBearbeitung::class)->bildLoeschen(3));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', 3, 'deleted'));
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/drei.jpg');
    }

    #[Test]
    public function bildLoeschenOhneDateiMeldetKeineDatei(): void
    {
        $this->meldeAn(1);
        $this->getConnectionPool()->getConnectionForTable('sys_file_reference')
            ->update('sys_file_reference', ['deleted' => 1], ['uid' => 2]);
        self::assertSame(LoeschErgebnis::KeineDatei, $this->get(GalerieBearbeitung::class)->bildLoeschen(2));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', 2, 'deleted'));
    }

    #[Test]
    public function redakteurOhneRechteKannNichtsAendern(): void
    {
        $this->meldeAn(2);
        try {
            $this->get(GalerieBearbeitung::class)->galerieSichtbarkeit(1, false);
            self::fail('Erwartete GalerieBearbeitungFehler');
        } catch (GalerieBearbeitungFehler $fehler) {
            self::assertNotSame('', $fehler->getMessage());
        }
        self::assertSame(0, (int)$this->feld('tx_crgalerie_domain_model_galerie', 1, 'hidden'));
    }

    #[Test]
    public function bildLoeschenBehaeltDatensatzGeloeschtWennDateiNichtGeloeschtWerdenDarf(): void
    {
        $this->meldeAn(1);
        // GalerieDateiablage ist final: echte Instanz, aber die Datei verweigert das Löschen.
        $datei = $this->createMock(File::class);
        $datei->method('delete')->willThrowException(
            new InsufficientFileAccessPermissionsException('Keine Löschrechte', 1760600099)
        );
        $resourceFactory = $this->createMock(ResourceFactory::class);
        $resourceFactory->method('getFileObject')->willReturn($datei);
        $dateiablage = new GalerieDateiablage(
            $this->get(ExtensionKonfiguration::class),
            $resourceFactory,
            $this->get(ConnectionPool::class),
            $this->get(StorageRepository::class),
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $bearbeitung = new GalerieBearbeitung(
            $this->get(ModulAbfragen::class),
            $dateiablage,
            $this->get(ConnectionPool::class),
            $logger,
        );

        self::assertSame(LoeschErgebnis::DateiNichtGeloescht, $bearbeitung->bildLoeschen(1));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', 1, 'deleted'));
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/eins.jpg');
    }

    #[Test]
    public function redakteurMitRechtenBearbeitetGalerieAufSeite(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ModulRedakteurMitRechten.csv');
        $this->meldeAn(4);
        $bearbeitung = $this->get(GalerieBearbeitung::class);

        $uid = $bearbeitung->bildAnlegen(4, 20, 2, 'Vom Redakteur');
        self::assertSame(4, (int)$this->feld('tx_crgalerie_domain_model_bilder', $uid, 'galerie'));
        self::assertSame(20, (int)$this->feld('tx_crgalerie_domain_model_bilder', $uid, 'pid'));
        self::assertSame(3, (int)$this->feld('tx_crgalerie_domain_model_bilder', $uid, 'sortierung'));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', $uid, 'datei'));

        $bearbeitung->bilderSortieren(4, [21, 20]);
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', 21, 'sortierung'));
        self::assertSame(2, (int)$this->feld('tx_crgalerie_domain_model_bilder', 20, 'sortierung'));

        $bearbeitung->bildSichtbarkeit(20, false);
        $bearbeitung->galerieSichtbarkeit(4, false);
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_bilder', 20, 'hidden'));
        self::assertSame(1, (int)$this->feld('tx_crgalerie_domain_model_galerie', 4, 'hidden'));
    }
}
