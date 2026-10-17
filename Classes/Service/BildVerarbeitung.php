<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

use Conrat\Crgalerie\Configuration\ExtensionKonfiguration;
use Conrat\Crgalerie\Domain\Dto\VerarbeitetesBild;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Bereitet ein hochgeladenes Bild für die Galerie auf: Format prüfen (am Inhalt, nicht an der Endung),
 * Pixelzahl begrenzen, nach EXIF drehen, EXIF/GPS entfernen, auf die maximale Größe verkleinern,
 * HEIC/HEIF in JPG umwandeln.
 * Nutzt den im Install Tool konfigurierten Prozessor (ImageMagick/GraphicsMagick).
 */
final class BildVerarbeitung
{
    /** Am Dateianfang erkanntes Format → Endung der gespeicherten Datei */
    private const ERLAUBT = ['JPEG' => 'jpg', 'PNG' => 'png', 'WEBP' => 'webp', 'HEIC' => 'jpg'];
    /** Erkanntes Format → Coder-Präfix für identify/convert (verhindert, dass ImageMagick selbst ein Format rät) */
    private const CODER = ['JPEG' => 'jpeg', 'PNG' => 'png', 'WEBP' => 'webp', 'HEIC' => 'heic'];
    /** ISO-BMFF-Brands von HEIC/HEIF-Bildern (Major- oder kompatible Brand im ftyp-Kasten) */
    private const HEIF_BRANDS = ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'];
    private const MAX_PIXEL = 100_000_000;
    private const MAX_NAMENSLAENGE = 100;

    public function __construct(
        private readonly ExtensionKonfiguration $konfiguration,
        private readonly CharsetConverter $charsetConverter,
        private readonly LoggerInterface $logger,
    ) {}

    public function verarbeite(string $quellPfad, string $originalName): VerarbeitetesBild
    {
        if (!($GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled'] ?? false)) {
            throw new BildVerarbeitungFehler('error.processorDisabled', 500, 'Image processing is disabled in TYPO3.');
        }
        $istHeicName = preg_match('/\.(heic|heif)$/i', $originalName) === 1;
        $formatFehler = $istHeicName ? self::heicFehler() : new BildVerarbeitungFehler(
            'error.format',
            415,
            'This file format is not supported. Allowed are JPG, PNG, WebP and HEIC.'
        );
        // Erst am Dateianfang prüfen, damit fremde Inhalte (SVG, PostScript, MVG …) ImageMagick gar nicht erreichen.
        $format = self::formatAmDateianfang($quellPfad) ?? throw $formatFehler;
        $eingabe = self::CODER[$format] . ':' . $quellPfad . '[0]';
        $abmessungen = $this->abmessungen($eingabe) ?? throw $formatFehler;
        if ($abmessungen[0] * $abmessungen[1] > self::MAX_PIXEL) {
            throw new BildVerarbeitungFehler('error.pixel', 415, 'The image is too large (more than 100 megapixels).');
        }
        $endung = self::ERLAUBT[$format];
        $ziel = GeneralUtility::tempnam('crgalerie_', '.' . $endung);
        $max = $this->konfiguration->getMaxBildgroesse();
        // „[0]“ wählt bei mehrbildigen Dateien (z. B. HEIC mit Zusatzbildern, animiertes WebP) nur das erste Bild,
        // sonst entstehen mehrere Zieldateien (ziel-0.jpg, ziel-1.jpg …). Gilt für ImageMagick und GraphicsMagick.
        // Die EXIF-Drehung übernimmt TYPO3: CommandUtility::imageMagickCommand() setzt bei „convert“ automatisch
        // „-auto-orient“ vor die Parameter; deshalb hier bewusst kein eigenes.
        // „-strip“ entfernt EXIF (u. a. GPS-Position), IPTC und XMP unabhängig von
        // GFX.processor_stripColorProfileByDefault; neben dem Core-Zusatz „+profile '*'“ ist es unschädlich.
        $parameter = CommandUtility::escapeShellArgument($eingabe)
            . ' -resize ' . CommandUtility::escapeShellArgument($max . 'x' . $max . '>')
            . ' -strip -quality 85 '
            . CommandUtility::escapeShellArgument($ziel);
        $ausgabe = [];
        $status = 0;
        CommandUtility::exec(CommandUtility::imageMagickCommand('convert', $parameter), $ausgabe, $status);
        $groesse = is_file($ziel) ? @getimagesize($ziel) : false;
        if ($status !== 0 || $groesse === false) {
            $this->raeumeAuf($ziel);
            $this->logger->error('crgalerie: Bild konnte nicht verarbeitet werden', ['datei' => $originalName, 'ausgabe' => $ausgabe]);
            if ($format === 'HEIC') {
                throw self::heicFehler();
            }
            throw new BildVerarbeitungFehler('error.processing', 500, 'The image could not be processed.');
        }
        return new VerarbeitetesBild($ziel, $this->dateiname($originalName, $endung), (int)$groesse[0], (int)$groesse[1]);
    }

    private static function heicFehler(): BildVerarbeitungFehler
    {
        return new BildVerarbeitungFehler('error.heic', 415, 'This server cannot convert HEIC images.');
    }

    /**
     * „Burg_Tor-im_Abendlicht.jpg“ → „Burg Tor im Abendlicht“
     */
    public static function titelAusDateiname(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = preg_replace('/[_\-]+/u', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /**
     * Erkennt JPEG, PNG, WebP und HEIC/HEIF an den ersten Bytes („Magic Bytes“); alles andere ergibt null.
     */
    private static function formatAmDateianfang(string $pfad): ?string
    {
        $handle = @fopen($pfad, 'rb');
        if ($handle === false) {
            return null;
        }
        $kopf = (string)fread($handle, 64);
        fclose($handle);
        if (str_starts_with($kopf, "\xFF\xD8\xFF")) {
            return 'JPEG';
        }
        if (str_starts_with($kopf, "\x89PNG\r\n\x1A\n")) {
            return 'PNG';
        }
        if (strlen($kopf) >= 12 && str_starts_with($kopf, 'RIFF') && substr($kopf, 8, 4) === 'WEBP') {
            return 'WEBP';
        }
        if (strlen($kopf) >= 16 && substr($kopf, 4, 4) === 'ftyp') {
            // ftyp-Kasten: Länge, „ftyp“, Major-Brand, Minor-Version, danach kompatible Brands (je 4 Byte)
            $laenge = min((int)unpack('N', substr($kopf, 0, 4))[1], strlen($kopf));
            $brands = [substr($kopf, 8, 4)];
            for ($position = 16; $position + 4 <= $laenge; $position += 4) {
                $brands[] = substr($kopf, $position, 4);
            }
            if (array_intersect($brands, self::HEIF_BRANDS) !== []) {
                return 'HEIC';
            }
        }
        return null;
    }

    /**
     * Liest nur die Kopfdaten („-ping“), ohne das Bild zu dekodieren.
     *
     * @return array{int, int}|null Breite und Höhe
     */
    private function abmessungen(string $eingabe): ?array
    {
        $ausgabe = [];
        $status = 0;
        $befehl = CommandUtility::imageMagickCommand(
            'identify',
            '-ping -format ' . CommandUtility::escapeShellArgument('%m %w %h\n') . ' ' . CommandUtility::escapeShellArgument($eingabe)
        );
        CommandUtility::exec($befehl . ' 2>/dev/null', $ausgabe, $status);
        if ($status !== 0 || preg_match('/^\S+ (\d+) (\d+)$/', trim((string)($ausgabe[0] ?? '')), $treffer) !== 1) {
            return null;
        }
        return [(int)$treffer[1], (int)$treffer[2]];
    }

    /**
     * Löscht die Zieldatei samt eventuell entstandener Einzelbilder („name-0.jpg“, „name-1.jpg“ …).
     */
    private function raeumeAuf(string $ziel): void
    {
        @unlink($ziel);
        $teile = pathinfo($ziel);
        foreach (glob($teile['dirname'] . '/' . $teile['filename'] . '-*.' . $teile['extension']) ?: [] as $rest) {
            @unlink($rest);
        }
    }

    private function dateiname(string $originalName, string $endung): string
    {
        $basis = pathinfo($originalName, PATHINFO_FILENAME);
        $basis = $this->charsetConverter->utf8_char_mapping($basis);
        $basis = preg_replace('/[^A-Za-z0-9._-]+/', '-', $basis) ?? '';
        $basis = trim(preg_replace('/-+/', '-', $basis) ?? '', '-.');
        $basis = rtrim(substr($basis, 0, self::MAX_NAMENSLAENGE), '-.');
        return ($basis !== '' ? $basis : 'bild') . '.' . $endung;
    }
}
