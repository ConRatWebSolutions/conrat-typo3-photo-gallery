<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Domain\Dto\VerarbeitetesBild;
use Conrat\Crgalerie\Service\GalerieDateiablage;
use Conrat\Crgalerie\Tests\Functional\BackendBenutzerTrait;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class GalerieDateiablageTest extends FunctionalTestCase
{
    use BackendBenutzerTrait;
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conrat/crgalerie'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Modul.csv');
        $this->meldeAn(1);
        $ordner = Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/';
        @mkdir($ordner, 0777, true);
        foreach (['eins', 'zwei', 'drei'] as $name) {
            $this->erzeugeTestbild($ordner . $name . '.jpg', 60, 40);
        }
    }

    private function temporaeresBild(string $name): VerarbeitetesBild
    {
        $verzeichnis = Environment::getVarPath() . '/transient/';
        @mkdir($verzeichnis, 0777, true);
        $pfad = $this->erzeugeTestbild($verzeichnis . uniqid('t', true) . '.jpg', 60, 40);
        return new VerarbeitetesBild($pfad, $name, 60, 40);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function ordnerNamen(): array
    {
        return [
            'normal' => [12, 'Sommerfest 2026', '12-sommerfest-2026'],
            'Umlaute' => [12, 'Sommerfest über alles!', '12-sommerfest-ueber-alles'],
            'leer' => [12, '   ', '12-galerie'],
            'lang' => [3, str_repeat('Burg ', 20), '3-' . substr(str_repeat('burg-', 8), 0, 40)],
        ];
    }

    #[Test]
    #[DataProvider('ordnerNamen')]
    public function ordnerNameIstGueltig(int $uid, string $titel, string $erwartet): void
    {
        self::assertSame(rtrim($erwartet, '-'), GalerieDateiablage::ordnerName($uid, $titel));
    }

    #[Test]
    public function vorhandenerOrdnerMitUidPraefixWirdWiederverwendet(): void
    {
        $ordner = $this->get(GalerieDateiablage::class)->ordnerFuer(1, 'Ganz anderer Titel');
        self::assertSame('/galerien/1-sommerfest-2026/', $ordner->getIdentifier());
    }

    #[Test]
    public function neuerOrdnerWirdAngelegt(): void
    {
        $ordner = $this->get(GalerieDateiablage::class)->ordnerFuer(7, 'Neue Galerie');
        self::assertSame('/galerien/7-neue-galerie/', $ordner->getIdentifier());
        self::assertDirectoryExists(Environment::getPublicPath() . '/fileadmin/galerien/7-neue-galerie');
    }

    #[Test]
    public function ablegenBenenntBeiNamenskonfliktUm(): void
    {
        $ablage = $this->get(GalerieDateiablage::class);
        $erste = $ablage->ablegen($this->temporaeresBild('foto.jpg'), 7, 'Neue Galerie');
        $zweite = $ablage->ablegen($this->temporaeresBild('foto.jpg'), 7, 'Neue Galerie');
        self::assertSame('foto.jpg', $erste->getName());
        self::assertNotSame('foto.jpg', $zweite->getName());
        self::assertStringStartsWith('/galerien/7-neue-galerie/', $zweite->getIdentifier());
    }

    #[Test]
    public function loescheWennUnbenutztLoeschtNurOhneAktiveReferenz(): void
    {
        $verbindung = $this->getConnectionPool()->getConnectionForTable('sys_file_reference');
        $verbindung->update('sys_file_reference', ['deleted' => 1], ['uid' => 1]);
        $verbindung->update('sys_file_reference', ['deleted' => 1], ['uid' => 3]);

        $ablage = $this->get(GalerieDateiablage::class);
        self::assertTrue($ablage->loescheWennUnbenutzt(1));
        self::assertFileDoesNotExist(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/eins.jpg');

        // Datei 3 wird noch von tt_content 5 verwendet
        self::assertFalse($ablage->loescheWennUnbenutzt(3));
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/drei.jpg');

        // Datei 2 hat noch die aktive Galerie-Referenz 2
        self::assertFalse($ablage->loescheWennUnbenutzt(2));
    }

    #[Test]
    public function loescheWennUnbenutztBehaeltPerLinkVerwendeteDatei(): void
    {
        // Datei 1 hat keine aktive Dateireferenz mehr, wird aber in einem Text per t3://file verlinkt.
        $this->getConnectionPool()->getConnectionForTable('sys_file_reference')
            ->update('sys_file_reference', ['deleted' => 1], ['uid' => 1]);
        $this->getConnectionPool()->getConnectionForTable('pages')
            ->insert('pages', ['uid' => 30, 'pid' => 0, 'title' => 'Inhalt', 'doktype' => 1]);
        $this->getConnectionPool()->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 40,
            'pid' => 30,
            'CType' => 'text',
            'bodytext' => '<p><a href="t3://file?uid=1">Programm als Bild</a></p>',
        ]);
        $this->get(ReferenceIndex::class)->updateRefIndexTable('tt_content', 40);

        $ablage = $this->get(GalerieDateiablage::class);
        self::assertFalse($ablage->loescheWennUnbenutzt(1));
        self::assertFileExists(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/eins.jpg');

        // Ohne den Link ist die Datei frei und wird gelöscht.
        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->update('tt_content', ['bodytext' => '<p>ohne Link</p>'], ['uid' => 40]);
        $this->get(ReferenceIndex::class)->updateRefIndexTable('tt_content', 40);
        self::assertTrue($ablage->loescheWennUnbenutzt(1));
        self::assertFileDoesNotExist(Environment::getPublicPath() . '/fileadmin/galerien/1-sommerfest-2026/eins.jpg');
    }
}
