<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

use Conrat\Crgalerie\Domain\Dto\BildAnzeige;
use Conrat\Crgalerie\Domain\Model\Bilder;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Bereitet Bilder für die Ausgabe auf: Titel und Beschreibung (Metadaten vor Datensatz),
 * Urheber/Lizenz aus konfigurierbaren sys_file_metadata-Feldern, sonst aus „(c) …“ im Text.
 * Ein Titel, der nur aus dem Dateinamen besteht (z. B. „IMG 1234“ nach dem Upload), gilt als leer.
 */
final class BildAnzeigeService
{
    private const COPYRIGHT_MUSTER = '/\s*(?:©|[\(\[]\s*[cC]\s*[\)\]])\s*(.*)$/u';

    /** @var array<string, true> */
    private array $gewarnt = [];

    /** @var array<int, true> */
    private array $gewarntBilder = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CharsetConverter $charsetConverter,
    ) {}

    /**
     * @param iterable<Bilder> $bilder
     * @return list<BildAnzeige>
     */
    public function fuerBilder(iterable $bilder, string $creditFeld = '', string $lizenzFeld = '', string $standardUrheber = ''): array
    {
        $ergebnis = [];
        foreach ($bilder as $bild) {
            $anzeige = $this->fuerBild($bild, $creditFeld, $lizenzFeld, $standardUrheber);
            if ($anzeige !== null) {
                $ergebnis[] = $anzeige;
            }
        }
        return $ergebnis;
    }

    private function fuerBild(Bilder $bild, string $creditFeld, string $lizenzFeld, string $standardUrheber): ?BildAnzeige
    {
        $referenz = $bild->getDatei();
        if ($referenz === null) {
            return null;
        }
        $datei = $bild->verfuegbareDatei();
        if ($datei === null) {
            $this->warneEinmal($bild, 'Datei fehlt oder ist nicht lesbar');
            return null;
        }
        try {
            $metadaten = $datei->getMetaData()->get();
        } catch (\Throwable $fehler) {
            $this->warneEinmal($bild, 'Metadaten nicht lesbar: ' . $fehler->getMessage());
            return null;
        }

        $titel = $this->text($metadaten, 'title') !== '' ? $this->text($metadaten, 'title') : $bild->getTitel();
        if ($this->istDateinamenTitel($titel, $datei)) {
            $titel = '';
        }
        $beschreibung = $this->text($metadaten, 'description') !== ''
            ? $this->text($metadaten, 'description')
            : $bild->getBildunterschrift();

        $urheber = $this->konfiguriertesFeld($metadaten, $creditFeld);
        $lizenz = $this->konfiguriertesFeld($metadaten, $lizenzFeld);

        [$titel, $urheberAusTitel] = self::trenneCopyright($titel);
        [$beschreibung, $urheberAusText] = self::trenneCopyright($beschreibung);
        if ($urheber === '') {
            $urheber = $urheberAusTitel !== '' ? $urheberAusTitel : $urheberAusText;
        }
        if ($urheber === '') {
            $urheber = trim($standardUrheber);
        }

        $credit = $urheber === '' ? '' : trim(($lizenz !== '' ? $lizenz . ' ' : '') . '© ' . $urheber);

        return new BildAnzeige(
            $referenz,
            self::trimmeRand($titel),
            self::trimmeRand($beschreibung),
            $credit,
        );
    }

    /**
     * Der Upload setzt den Titel aus dem Originalnamen („bu_0010.jpg“ → „bu 0010“). Gespeichert wird die Datei unter
     * einem bereinigten Namen (Umlaute umschrieben, Sonderzeichen → „-“, bei Dubletten „_01“), daher werden beide
     * Seiten gleich normalisiert verglichen: umschreiben, Kleinbuchstaben, alles außer Buchstaben/Ziffern → Leerzeichen. Bleibt vom Titel
     * nichts übrig (nur Symbole), gilt er nicht als Dateiname.
     */
    private function istDateinamenTitel(string $titel, FileInterface $datei): bool
    {
        if (trim($titel) === '') {
            return false;
        }
        $name = pathinfo($datei->getName(), PATHINFO_FILENAME);
        $kandidaten = [$name, BildVerarbeitung::titelAusDateiname($datei->getName())];
        $ohneDublettenZaehler = (string)preg_replace('/_[0-9]{2}$/', '', $name);
        if ($ohneDublettenZaehler !== $name) {
            $kandidaten[] = $ohneDublettenZaehler;
        }
        $vergleich = $this->normalisiere($titel);
        if ($vergleich === '') {
            return false;
        }
        foreach ($kandidaten as $kandidat) {
            if ($vergleich === $this->normalisiere($kandidat)) {
                return true;
            }
        }
        return false;
    }

    private function normalisiere(string $text): string
    {
        // dieselbe Umschreibung wie beim Speichern (BildVerarbeitung::dateiname): ä → ae, ß → ss …
        $text = mb_strtolower($this->charsetConverter->utf8_char_mapping($text), 'UTF-8');
        return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }

    private function warneEinmal(Bilder $bild, string $grund): void
    {
        $uid = (int)$bild->getUid();
        if (isset($this->gewarntBilder[$uid])) {
            return;
        }
        $this->gewarntBilder[$uid] = true;
        $this->logger->warning(sprintf('crgalerie: Bild %d wird übersprungen (%s).', $uid, $grund));
    }

    /**
     * @param array<string, mixed> $metadaten
     */
    private function konfiguriertesFeld(array $metadaten, string $feld): string
    {
        if ($feld === '') {
            return '';
        }
        if (!array_key_exists($feld, $metadaten)) {
            if (!isset($this->gewarnt[$feld])) {
                $this->gewarnt[$feld] = true;
                $this->logger->warning(sprintf('crgalerie: Metadatenfeld "%s" existiert nicht in sys_file_metadata.', $feld));
            }
            return '';
        }
        return $this->text($metadaten, $feld);
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function text(array $daten, string $feld): string
    {
        $wert = $daten[$feld] ?? '';
        return is_scalar($wert) ? trim((string)$wert) : '';
    }

    /**
     * „Text (c) Name“ → ['Text', 'Name']
     *
     * @return array{0: string, 1: string}
     */
    public static function trenneCopyright(string $text): array
    {
        if ($text === '' || !preg_match(self::COPYRIGHT_MUSTER, $text, $treffer, PREG_OFFSET_CAPTURE)) {
            return [$text, ''];
        }
        $rest = trim(substr($text, 0, (int)$treffer[0][1]));
        $name = trim(rtrim($treffer[1][0], ' )]'));
        return [$rest, $name];
    }

    /**
     * Entfernt Leerraum und Trennstriche am Rand (UTF-8-sicher).
     */
    public static function trimmeRand(string $text): string
    {
        return (string)preg_replace('/^[\s\-–—]+|[\s\-–—]+$/u', '', $text);
    }
}
