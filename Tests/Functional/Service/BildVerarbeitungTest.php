<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Service\BildVerarbeitung;
use Conrat\Crgalerie\Service\BildVerarbeitungFehler;
use Conrat\Crgalerie\Tests\Functional\TestbildTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BildVerarbeitungTest extends FunctionalTestCase
{
    use TestbildTrait;

    protected array $testExtensionsToLoad = ['conrat/crgalerie'];

    protected array $configurationToUseInTestInstance = [
        'GFX' => [
            'processor' => 'ImageMagick',
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
        parent::setUp();
        $this->verzeichnis = Environment::getVarPath() . '/transient/bildtest/';
        @mkdir($this->verzeichnis, 0777, true);
    }

    private function verarbeitung(): BildVerarbeitung
    {
        return $this->get(BildVerarbeitung::class);
    }

    #[Test]
    public function grossesQuerformatWirdAufMaximumVerkleinert(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'gross.jpg', 3000, 2000);
        $bild = $this->verarbeitung()->verarbeite($quelle, 'Sommer Fest.jpg');
        self::assertSame(1000, $bild->breite);
        self::assertSame(667, $bild->hoehe);
        self::assertSame('Sommer-Fest.jpg', $bild->dateiname);
        self::assertFileExists($bild->pfad);
    }

    #[Test]
    public function kleinesBildWirdNichtVergroessert(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'klein.png', 400, 300);
        $bild = $this->verarbeitung()->verarbeite($quelle, 'klein.png');
        self::assertSame([400, 300], [$bild->breite, $bild->hoehe]);
        self::assertStringEndsWith('.png', $bild->dateiname);
    }

    #[Test]
    public function exifDrehungWirdAngewendet(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'gedreht.jpg', 300, 200, '', 6);
        $bild = $this->verarbeitung()->verarbeite($quelle, 'gedreht.jpg');
        self::assertSame([200, 300], [$bild->breite, $bild->hoehe]);
    }

    #[Test]
    public function webpBleibtWebp(): void
    {
        if (!$this->formatVerfuegbar('WEBP')) {
            self::markTestSkipped('WebP wird vom ImageMagick dieser Umgebung nicht unterstützt.');
        }
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'bild.webp', 1200, 800);
        $bild = $this->verarbeitung()->verarbeite($quelle, 'bild.webp');
        self::assertStringEndsWith('.webp', $bild->dateiname);
        self::assertSame(1000, $bild->breite);
    }

    #[Test]
    public function mehrbildigesHeicLiefertEineEinzigeJpgDatei(): void
    {
        if (!$this->formatVerfuegbar('HEIC')) {
            self::markTestSkipped('HEIC wird vom ImageMagick dieser Umgebung nicht unterstützt.');
        }
        // Zwei Bilder in einer Datei; ohne Auswahl des ersten Bildes entstünden name-0.jpg und name-1.jpg.
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'mehrbildig.heic', 50, 50, 'xc:red');
        $bild = $this->verarbeitung()->verarbeite($quelle, 'mehrbildig.heic');
        self::assertFileExists($bild->pfad);
        self::assertSame([50, 50], [$bild->breite, $bild->hoehe]);
        self::assertSame('mehrbildig.jpg', $bild->dateiname);
    }

    #[Test]
    public function heicWirdZuJpg(): void
    {
        if (!$this->formatVerfuegbar('HEIC')) {
            self::markTestSkipped('HEIC wird vom ImageMagick dieser Umgebung nicht unterstützt.');
        }
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'iphone.heic', 1200, 900);
        $bild = $this->verarbeitung()->verarbeite($quelle, 'IMG_0001.HEIC');
        self::assertSame('IMG_0001.jpg', $bild->dateiname);
        self::assertSame('image/jpeg', mime_content_type($bild->pfad));
    }

    #[Test]
    public function textdateiMitJpgEndungWirdAbgelehnt(): void
    {
        $quelle = $this->verzeichnis . 'falsch.jpg';
        file_put_contents($quelle, '<?php echo "kein Bild";');
        try {
            $this->verarbeitung()->verarbeite($quelle, 'falsch.jpg');
            self::fail('Erwartete BildVerarbeitungFehler');
        } catch (BildVerarbeitungFehler $fehler) {
            self::assertSame(415, $fehler->httpStatus);
            self::assertSame('error.format', $fehler->sprachschluessel);
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function fremdeInhalte(): array
    {
        $formatMeldung = 'error.format';
        return [
            'SVG als jpg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>', 'logo.jpg', $formatMeldung],
            'PostScript als jpg' => ["%!PS-Adobe-3.0\n0 0 moveto showpage\n", 'druck.jpg', $formatMeldung],
            'MVG als png' => ["push graphic-context\nviewbox 0 0 10 10\npop graphic-context\n", 'bild.png', $formatMeldung],
            'Text als heic' => ['kein Bild', 'IMG_0002.HEIC', 'error.heic'],
            'MP4 als heic' => ["\x00\x00\x00\x18ftypisom\x00\x00\x00\x00isommp41", 'film.heic', 'error.heic'],
        ];
    }

    #[Test]
    #[DataProvider('fremdeInhalte')]
    public function fremderInhaltWirdOhneImageMagickAbgelehnt(string $inhalt, string $name, string $meldung): void
    {
        [$pfad, $markierung] = $this->attrappenProzessor($this->verzeichnis . 'attrappe/');
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = $pfad;
        $quelle = $this->verzeichnis . 'fremd.bin';
        file_put_contents($quelle, $inhalt);
        try {
            $this->verarbeitung()->verarbeite($quelle, $name);
            self::fail('Erwartete BildVerarbeitungFehler');
        } catch (BildVerarbeitungFehler $fehler) {
            self::assertSame(415, $fehler->httpStatus);
            self::assertSame($meldung, $fehler->sprachschluessel);
        }
        self::assertFileDoesNotExist($markierung, 'ImageMagick/GraphicsMagick wurde trotzdem aufgerufen');
    }

    #[Test]
    public function bildMitMehrAls100MegapixelnWirdAbgelehnt(): void
    {
        $quelle = $this->erzeugeGrossesPng($this->verzeichnis . 'riesig.png', 12000, 9000);
        try {
            $this->verarbeitung()->verarbeite($quelle, 'riesig.png');
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
        self::assertArrayHasKey('Orientation', (array)@exif_read_data($quelle), 'Testbild ohne EXIF');
        $bild = $this->verarbeitung()->verarbeite($quelle, 'mitexif.jpg');
        $exif = @exif_read_data($bild->pfad);
        self::assertArrayNotHasKey('Orientation', is_array($exif) ? $exif : []);
        self::assertStringNotContainsString("Exif\x00\x00", (string)file_get_contents($bild->pfad));
    }

    #[Test]
    public function langerDateinameWirdGekuerzt(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'lang.jpg', 50, 50);
        $bild = $this->verarbeitung()->verarbeite($quelle, str_repeat('Ä', 80) . '.jpg');
        // „Ä“ wird zu „AE“: 160 Zeichen, gekürzt auf 100
        self::assertSame(str_repeat('AE', 50) . '.jpg', $bild->dateiname);
    }

    #[Test]
    public function gifWirdAbgelehnt(): void
    {
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'animiert.gif', 50, 50);
        $this->expectException(BildVerarbeitungFehler::class);
        $this->verarbeitung()->verarbeite($quelle, 'animiert.gif');
    }

    #[Test]
    public function deaktivierterProzessorWirdGemeldet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled'] = false;
        $quelle = $this->erzeugeTestbild($this->verzeichnis . 'egal.jpg', 10, 10);
        try {
            $this->verarbeitung()->verarbeite($quelle, 'egal.jpg');
            self::fail('Erwartete BildVerarbeitungFehler');
        } catch (BildVerarbeitungFehler $fehler) {
            self::assertSame('error.processorDisabled', $fehler->sprachschluessel);
            self::assertSame(500, $fehler->httpStatus);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dateinamen(): array
    {
        return [
            'Unterstriche' => ['Burg_Tor-im_Abendlicht.jpg', 'Burg Tor im Abendlicht'],
            'Umlaute' => ['Über die Brücke.jpeg', 'Über die Brücke'],
            'ohne Endung' => ['bild', 'bild'],
        ];
    }

    #[Test]
    #[DataProvider('dateinamen')]
    public function titelAusDateiname(string $name, string $erwartet): void
    {
        self::assertSame($erwartet, BildVerarbeitung::titelAusDateiname($name));
    }
}
