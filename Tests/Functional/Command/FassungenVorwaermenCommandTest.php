<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Command;

use Conrat\Crgalerie\Command\FassungenVorwaermenCommand;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FassungenVorwaermenCommandTest extends FunctionalTestCase
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
        // Galerie 1 sichtbar mit Bildern 1 und 3 (Dateien fehlen auf der Platte) und Bild 2 (ausgeblendet),
        // Galerie 2 ausgeblendet
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Modul.csv');
    }

    private function bildMitDatei(int $bild, int $galerie, string $name): File
    {
        $verzeichnis = Environment::getVarPath() . '/transient/vorwaermen/';
        @mkdir($verzeichnis, 0777, true);
        $pfad = $this->erzeugeTestbild($verzeichnis . $name, 3000, 2000);
        $speicher = $this->get(StorageRepository::class)->findByUid(1);
        self::assertNotNull($speicher);
        $ordner = $speicher->hasFolder('/vorwaermen/') ? $speicher->getFolder('/vorwaermen/') : $speicher->createFolder('/vorwaermen/');
        $datei = $ordner->addFile($pfad, $name);
        self::assertInstanceOf(File::class, $datei);
        $pool = $this->getConnectionPool();
        $pool->getConnectionForTable('tx_crgalerie_domain_model_bilder')->insert('tx_crgalerie_domain_model_bilder', [
            'uid' => $bild, 'pid' => 0, 'titel' => $name, 'sortierung' => $bild, 'datei' => 1, 'galerie' => $galerie,
        ]);
        $pool->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', [
            'pid' => 0, 'uid_local' => $datei->getUid(), 'uid_foreign' => $bild,
            'tablenames' => 'tx_crgalerie_domain_model_bilder', 'fieldname' => 'datei',
        ]);
        return $datei;
    }

    private function anzahlFassungen(File $datei): int
    {
        return (int)$this->getConnectionPool()->getConnectionForTable('sys_file_processedfile')
            ->count('uid', 'sys_file_processedfile', ['original' => $datei->getUid()]);
    }

    private function tester(): CommandTester
    {
        return new CommandTester($this->get(FassungenVorwaermenCommand::class));
    }

    #[Test]
    public function erzeugtFassungenFuerSichtbareBilder(): void
    {
        $sichtbar = $this->bildMitDatei(10, 1, 'sichtbar.jpg');
        $versteckteGalerie = $this->bildMitDatei(11, 2, 'versteckte-galerie.jpg');
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(4, $this->anzahlFassungen($sichtbar));
        self::assertSame(0, $this->anzahlFassungen($versteckteGalerie));
        $ausgabe = $tester->getDisplay();
        self::assertStringContainsString('1 image prepared', $ausgabe);
        // Bilder 1 und 3 verweisen auf Dateien, die es auf der Platte nicht gibt
        self::assertStringContainsString('2 images skipped', $ausgabe);
    }

    #[Test]
    public function galerieOptionBeschraenktAufEineGalerie(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_crgalerie_domain_model_galerie')
            ->insert('tx_crgalerie_domain_model_galerie', ['uid' => 5, 'pid' => 0, 'titel' => 'Fünf']);
        $andere = $this->bildMitDatei(10, 1, 'andere.jpg');
        $gewaehlt = $this->bildMitDatei(12, 5, 'gewaehlt.jpg');
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--galerie' => '5']));
        self::assertSame(4, $this->anzahlFassungen($gewaehlt));
        self::assertSame(0, $this->anzahlFassungen($andere));
        self::assertStringContainsString('1 image prepared', $tester->getDisplay());
    }

    #[Test]
    public function ungueltigeGalerieOptionIstFehler(): void
    {
        self::assertSame(Command::INVALID, $this->tester()->execute(['--galerie' => 'abc']));
    }
}
