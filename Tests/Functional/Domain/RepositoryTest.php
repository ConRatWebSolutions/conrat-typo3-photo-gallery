<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Domain;

use Conrat\Crgalerie\Domain\Model\Bilder;
use Conrat\Crgalerie\Domain\Model\Galerie;
use Conrat\Crgalerie\Domain\Repository\BilderRepository;
use Conrat\Crgalerie\Domain\Repository\GalerieRepository;
use Conrat\Crgalerie\Domain\Sortierung;
use Conrat\Crgalerie\Tests\Functional\FrontendKontextTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RepositoryTest extends FunctionalTestCase
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

    #[Test]
    public function findSichtbareLiefertSichtbareGalerienMitBildernNachSortierung(): void
    {
        $titel = array_map(
            static fn(Galerie $g): string => $g->getTitel(),
            $this->get(GalerieRepository::class)->findSichtbare()
        );
        self::assertSame(['Werratal', 'Sommerfest'], $titel);
    }

    #[Test]
    public function findSichtbareFiltertNachKategorie(): void
    {
        $titel = array_map(
            static fn(Galerie $g): string => $g->getTitel(),
            $this->get(GalerieRepository::class)->findSichtbare(1)
        );
        self::assertSame(['Sommerfest'], $titel);
    }

    #[Test]
    public function findEineSichtbareIgnoriertVersteckteUndUnbekannte(): void
    {
        $repository = $this->get(GalerieRepository::class);
        self::assertSame('Sommerfest', $repository->findEineSichtbare(1)?->getTitel());
        self::assertNull($repository->findEineSichtbare(3));
        self::assertNull($repository->findEineSichtbare(999));
    }

    #[Test]
    public function galerieZaehltNurSichtbareBilderUndLiefertErstesBild(): void
    {
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(1);
        self::assertNotNull($galerie);
        // „Ohne Datei“ verweist auf eine als fehlend markierte Datei und zählt nicht mit
        self::assertSame(3, $galerie->getAnzahlBilder());
        self::assertSame('Alpha', $galerie->getErstesBild()?->getTitel());
    }

    #[Test]
    public function erstesBildUeberspringtVerweisAufNichtVorhandeneDatei(): void
    {
        // Werratal: „Kaputt“ (Position 0) verweist auf eine sys_file-uid, die es nicht gibt
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(2);
        self::assertNotNull($galerie);
        self::assertSame(1, $galerie->getAnzahlBilder());
        self::assertSame('Fluss', $galerie->getErstesBild()?->getTitel());
    }

    #[Test]
    public function galerieNurMitFehlendenDateienIstNichtSichtbar(): void
    {
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(5);
        self::assertNotNull($galerie);
        self::assertSame(0, $galerie->getAnzahlBilder());
        self::assertNull($galerie->getErstesBild());
        self::assertNotContains(
            'Nur fehlende Dateien',
            array_map(static fn(Galerie $g): string => $g->getTitel(), $this->get(GalerieRepository::class)->findSichtbare())
        );
    }

    #[Test]
    public function bilderManuellAufsteigendMitUidAlsZweitemKriterium(): void
    {
        self::assertSame(['Alpha', 'Beta', 'Zelt', 'Ohne Datei'], $this->bildTitel(Sortierung::ManuellAufsteigend));
    }

    #[Test]
    public function bilderManuellAbsteigend(): void
    {
        self::assertSame(['Ohne Datei', 'Zelt', 'Beta', 'Alpha'], $this->bildTitel(Sortierung::ManuellAbsteigend));
    }

    #[Test]
    public function bilderNachTitel(): void
    {
        self::assertSame(['Alpha', 'Beta', 'Ohne Datei', 'Zelt'], $this->bildTitel(Sortierung::TitelAufsteigend));
        self::assertSame(['Zelt', 'Ohne Datei', 'Beta', 'Alpha'], $this->bildTitel(Sortierung::TitelAbsteigend));
    }

    /**
     * @return list<string>
     */
    private function bildTitel(Sortierung $sortierung): array
    {
        $galerie = $this->get(GalerieRepository::class)->findEineSichtbare(1);
        self::assertNotNull($galerie);
        return array_map(
            static fn(Bilder $b): string => $b->getTitel(),
            $this->get(BilderRepository::class)->findByGalerie($galerie, $sortierung)
        );
    }
}
