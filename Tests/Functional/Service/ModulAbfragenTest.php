<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Service\ModulAbfragen;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ModulAbfragenTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['conrat/crgalerie'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Modul.csv');
    }

    #[Test]
    public function uebersichtZaehltUndFindetTitelbild(): void
    {
        $galerien = $this->get(ModulAbfragen::class)->galerienUebersicht();
        self::assertSame([1, 2], array_column($galerien, 'uid'));
        self::assertSame(3, $galerien[0]['anzahl']);
        self::assertSame(1, $galerien[0]['ausgeblendet']);
        self::assertSame(1, $galerien[0]['titelbild']);
        self::assertSame('Veranstaltungen', $galerien[0]['kategorie']);
        self::assertSame(0, $galerien[1]['anzahl']);
        self::assertSame(0, $galerien[1]['titelbild']);
        self::assertSame(1, $galerien[1]['hidden']);
    }

    #[Test]
    public function galerieLiefertAuchAusgeblendeteMitStatus(): void
    {
        $abfragen = $this->get(ModulAbfragen::class);
        self::assertSame('fhidden', $abfragen->galerie(2)['status'] ?? null);
        self::assertSame('Anna Beispiel', $abfragen->galerie(1)['fotograf'] ?? null);
        self::assertNull($abfragen->galerie(99));
    }

    #[Test]
    public function bilderEnthaltenVersteckteUndVerweise(): void
    {
        $bilder = $this->get(ModulAbfragen::class)->bilder(1);
        self::assertSame([1, 2, 3], array_column($bilder, 'uid'));
        self::assertSame('fhidden', $bilder[1]['status']);
        self::assertSame(1, $bilder[0]['datei']);
        self::assertSame(1, $bilder[0]['metadaten']);
        self::assertSame(0, $bilder[1]['metadaten']);
    }

    #[Test]
    public function titelbildUeberspringtFehlendeDatei(): void
    {
        $this->getConnectionPool()->getConnectionForTable('sys_file')->update('sys_file', ['missing' => 1], ['uid' => 1]);
        $galerien = $this->get(ModulAbfragen::class)->galerienUebersicht();
        self::assertSame(3, $galerien[0]['titelbild']);
        self::assertSame(0, $this->get(ModulAbfragen::class)->bilder(1)[0]['datei']);
    }

    #[Test]
    public function naechsteSortierungHaengtHintenAn(): void
    {
        self::assertSame(4, $this->get(ModulAbfragen::class)->naechsteSortierung(1));
        self::assertSame(1, $this->get(ModulAbfragen::class)->naechsteSortierung(2));
    }
}
