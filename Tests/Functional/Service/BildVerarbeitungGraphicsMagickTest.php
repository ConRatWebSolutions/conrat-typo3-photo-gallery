<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Service\BildVerarbeitung;
use Conrat\Crgalerie\Service\BildVerarbeitungFehler;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Wie BildVerarbeitungTest, aber mit GraphicsMagick (so ist die Entwicklungsinstanz konfiguriert).
 */
final class BildVerarbeitungGraphicsMagickTest extends FunctionalTestCase
{
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];

    protected array $configurationToUseInTestInstance = [
        'GFX' => [
            'processor' => 'GraphicsMagick',
            'processor_path' => '/usr/bin/',
            'processor_enabled' => true,
        ],
        'EXTENSIONS' => [
            'crgalerie' => ['maxBildgroesse' => '1000'],
        ],
    ];

    private string $verzeichnis;

    protected function setUp(): void
    {
        if (!is_executable('/usr/bin/gm')) {
            self::markTestSkipped('GraphicsMagick (/usr/bin/gm) ist nicht installiert.');
        }
        parent::setUp();
        $this->verzeichnis = Environment::getVarPath() . '/transient/bildtest-gm/';
        @mkdir($this->verzeichnis, 0777, true);
    }

    #[Test]
    public function grossesBildWirdVerkleinert(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'gross.jpg', 3000, 2000);
        $bild = $this->get(BildVerarbeitung::class)->verarbeite($quelle, 'gross.jpg');
        self::assertSame([1000, 667], [$bild->breite, $bild->hoehe]);
    }

    #[Test]
    public function exifDrehungWirdAngewendet(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'gedreht.jpg', 300, 200, '', 6);
        $bild = $this->get(BildVerarbeitung::class)->verarbeite($quelle, 'gedreht.jpg');
        self::assertSame([200, 300], [$bild->breite, $bild->hoehe]);
    }

    #[Test]
    public function bildMitMehrAls100MegapixelnWirdAbgelehnt(): void
    {
        $quelle = $this->erzeugeGrossesPng($this->verzeichnis . 'riesig.png', 12000, 9000);
        try {
            $this->get(BildVerarbeitung::class)->verarbeite($quelle, 'riesig.png');
            self::fail('Erwartete BildVerarbeitungFehler');
        } catch (BildVerarbeitungFehler $fehler) {
            self::assertSame(415, $fehler->httpStatus);
            self::assertSame('error.pixel', $fehler->sprachschluessel);
        }
    }

    #[Test]
    public function exifDatenWerdenAuchOhneCoreStripEntfernt(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileByDefault'] = false;
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'mitexif.jpg', 300, 200, '', 1);
        $bild = $this->get(BildVerarbeitung::class)->verarbeite($quelle, 'mitexif.jpg');
        self::assertStringNotContainsString("Exif\x00\x00", (string)file_get_contents($bild->pfad));
    }

    #[Test]
    public function pngUndWebpWerdenVerarbeitet(): void
    {
        $png = $this->get(BildVerarbeitung::class)->verarbeite($this->erzeugeTestbild($this->verzeichnis . 'a.png', 40, 30), 'a.png');
        self::assertSame([40, 30, 'a.png'], [$png->breite, $png->hoehe, $png->dateiname]);
        $webp = $this->get(BildVerarbeitung::class)->verarbeite($this->erzeugeTestbild($this->verzeichnis . 'b.webp', 40, 30), 'b.webp');
        self::assertSame([40, 30, 'b.webp'], [$webp->breite, $webp->hoehe, $webp->dateiname]);
    }

    #[Test]
    public function heicWirdZuJpg(): void
    {
        if (!$this->formatVerfuegbar('HEIC')) {
            self::markTestSkipped('HEIC wird vom ImageMagick dieser Umgebung nicht unterstützt.');
        }
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'iphone.heic', 1200, 900);
        $bild = $this->get(BildVerarbeitung::class)->verarbeite($quelle, 'IMG_0001.HEIC');
        self::assertSame('IMG_0001.jpg', $bild->dateiname);
        self::assertSame([1000, 750], [$bild->breite, $bild->hoehe]);
    }
}
