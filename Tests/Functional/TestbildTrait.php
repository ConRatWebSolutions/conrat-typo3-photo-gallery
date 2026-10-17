<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional;

/**
 * Erzeugt Testbilder mit dem ImageMagick der Testumgebung (convert muss im PATH liegen).
 */
trait TestbildTrait
{
    private function erzeugeTestbild(string $ziel, int $breite, int $hoehe, string $zusatz = '', int $exifOrientierung = 0): string
    {
        $befehl = sprintf(
            'convert -size %dx%d xc:steelblue %s %s 2>&1',
            $breite,
            $hoehe,
            $zusatz,
            escapeshellarg($ziel)
        );
        exec($befehl, $ausgabe, $status);
        self::assertSame(0, $status, 'Testbild konnte nicht erzeugt werden: ' . implode("\n", $ausgabe));
        if ($exifOrientierung > 0) {
            // ImageMagick schreibt die Orientierung nicht in JPEG-EXIF, daher von Hand eintragen (z. B. 6 = RightTop).
            $this->schreibeExifOrientierung($ziel, $exifOrientierung);
        }
        return $ziel;
    }

    private function schreibeExifOrientierung(string $jpegPfad, int $orientierung): void
    {
        $jpeg = (string)file_get_contents($jpegPfad);
        self::assertSame("\xFF\xD8", substr($jpeg, 0, 2), 'Testbild ist kein JPEG');
        $tiff = "II*\x00\x08\x00\x00\x00" . pack('v', 1) . pack('vvVv', 0x0112, 3, 1, $orientierung) . "\x00\x00" . pack('V', 0);
        $app1 = "Exif\x00\x00" . $tiff;
        $segment = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
        file_put_contents($jpegPfad, "\xFF\xD8" . $segment . substr($jpeg, 2));
    }

    /**
     * Schwarzweiß-PNG mit großen Abmessungen, aber kleiner Datei (stark komprimierte Nullzeilen).
     * ImageMagick würde für „convert -size … xc:“ das ganze Bild im Speicher anlegen.
     */
    private function erzeugeGrossesPng(string $ziel, int $breite, int $hoehe): string
    {
        $block = static fn(string $typ, string $daten): string
            => pack('N', strlen($daten)) . $typ . $daten . pack('N', crc32($typ . $daten));
        $kontext = deflate_init(ZLIB_ENCODING_DEFLATE);
        self::assertNotFalse($kontext);
        $zeile = str_repeat("\x00", 1 + intdiv($breite + 7, 8));
        $komprimiert = '';
        for ($i = 0; $i < $hoehe; $i++) {
            $komprimiert .= (string)deflate_add($kontext, $zeile, ZLIB_NO_FLUSH);
        }
        $komprimiert .= (string)deflate_add($kontext, '', ZLIB_FINISH);
        file_put_contents(
            $ziel,
            "\x89PNG\r\n\x1a\n"
            . $block('IHDR', pack('NNCCCCC', $breite, $hoehe, 1, 0, 0, 0, 0))
            . $block('IDAT', $komprimiert)
            . $block('IEND', '')
        );
        return $ziel;
    }

    /**
     * Legt ein Verzeichnis mit Platzhaltern für identify/convert/gm an, die nur eine Markierungsdatei schreiben.
     * Als processor_path gesetzt, lässt sich so nachweisen, dass ImageMagick gar nicht aufgerufen wurde.
     *
     * @return array{string, string} Verzeichnis (mit Schrägstrich am Ende) und Pfad der Markierungsdatei
     */
    private function attrappenProzessor(string $verzeichnis): array
    {
        @mkdir($verzeichnis, 0777, true);
        $markierung = $verzeichnis . 'aufgerufen';
        @unlink($markierung);
        foreach (['identify', 'convert', 'gm'] as $programm) {
            file_put_contents($verzeichnis . $programm, "#!/bin/sh\ntouch " . escapeshellarg($markierung) . "\nexit 1\n");
            chmod($verzeichnis . $programm, 0755);
        }
        return [$verzeichnis, $markierung];
    }

    private function formatVerfuegbar(string $format): bool
    {
        exec('convert -list format 2>/dev/null', $ausgabe);
        foreach ($ausgabe as $zeile) {
            if (preg_match('/^\s*' . preg_quote($format, '/') . '\*?\s+\S+\s+rw/i', $zeile) === 1) {
                return true;
            }
        }
        return false;
    }
}
