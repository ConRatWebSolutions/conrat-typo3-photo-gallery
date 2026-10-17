<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * sizes für ein Bild im Mosaik in Zeilen. Die Bildbreite richtet sich dort nach Zeilenhöhe und Seitenverhältnis,
 * nicht nach der Viewportbreite – daher je Bild ein eigener Wert für alle Breakpoints:
 *   {crg:zeilenSizes(verhaeltnis: quellen.verhaeltnis)}
 *   → "(min-width: 600px) min(100vw, 462px), (min-resolution: 2.5dppx) min(100vw, 132px), min(100vw, 200px)"
 *     (Seitenverhältnis 1,5)
 *
 * Breite = Seitenverhältnis × Zeilenhöhe × Reserve. Die Reserve deckt das Strecken ab: Die Bilder einer Zeile werden
 * breiter, bis sie die Zeile füllen (gemessen bis ~1,1 auf 390-px-Handys, bis ~1,4 auf Tablet und Desktop).
 */
final class ZeilenSizesViewHelper extends AbstractViewHelper
{
    /** Standard-Zeilenhöhen aus galerie.css (--crg-zeilenhoehe ab 600 px, --crg-zeilenhoehe-mobil darunter) */
    public const ZEILENHOEHE = 220;
    public const ZEILENHOEHE_MOBIL = 100;

    /**
     * Handy, DPR unter 2,5: Strecken bis ~1,1, dazu Luft → 1,33 (bei 100 px Zeilenhöhe 133 px je Einheit). Geräte
     * mit DPR 2 bekommen so auch breite Bilder und Panoramen scharf.
     */
    public const RESERVE_MOBIL = 1.33;

    /**
     * Handy ab DPR 2,5: zwei Drittel davon. Ziel ist dort doppelte statt dreifache Pixeldichte, normale Quer- und
     * Hochformate kommen so mit der 400er-Fassung aus (Panoramen bekommen weiter die 800er).
     */
    public const RESERVE_MOBIL_HOHE_DICHTE = 0.88;

    /**
     * Tablet/Desktop: Strecken bis ~1,4 (Testseite bei 1280 px), bei 220 px Zeilenhöhe 308 px je Einheit.
     * Eine eigene, höhere Reserve für DPR 2 lohnt nicht: Chrome nimmt bei DPR > 1 die kleinere Fassung, solange die
     * Zieldichte unter dem geometrischen Mittel der Nachbarfassungen liegt. Um das auszugleichen (Reserve 1,8),
     * lüde die Testseite bei 1280 px/DPR 2 rund 70 % mehr; mit 1,4 werden einzelne Bilder höchstens ~1,17-fach
     * hochskaliert, die meisten gar nicht.
     */
    public const RESERVE = 1.4;

    public function initializeArguments(): void
    {
        $this->registerArgument('verhaeltnis', 'string', 'Seitenverhältnis Breite/Höhe (BildQuellen::$verhaeltnis)', true);
        $this->registerArgument('zeilenhoehe', 'int', 'Zeilenhöhe in px ab 600 px (wie --crg-zeilenhoehe)', false, self::ZEILENHOEHE);
        $this->registerArgument('zeilenhoeheMobil', 'int', 'Zeilenhöhe in px unter 600 px (wie --crg-zeilenhoehe-mobil)', false, self::ZEILENHOEHE_MOBIL);
    }

    public function render(): string
    {
        return self::sizes(
            (float)$this->arguments['verhaeltnis'],
            (int)$this->arguments['zeilenhoehe'],
            (int)$this->arguments['zeilenhoeheMobil'],
        );
    }

    public static function sizes(float $verhaeltnis, int $zeilenhoehe = self::ZEILENHOEHE, int $zeilenhoeheMobil = self::ZEILENHOEHE_MOBIL): string
    {
        if ($verhaeltnis <= 0) {
            $verhaeltnis = 1.5;
        }
        $zeilenhoehe = $zeilenhoehe > 0 ? $zeilenhoehe : self::ZEILENHOEHE;
        $zeilenhoeheMobil = $zeilenhoeheMobil > 0 ? $zeilenhoeheMobil : self::ZEILENHOEHE_MOBIL;
        $breite = static fn(int $hoehe, float $reserve): int => max(1, (int)round($verhaeltnis * $hoehe * $reserve));
        return sprintf(
            '(min-width: 600px) min(100vw, %dpx), (min-resolution: 2.5dppx) min(100vw, %dpx), min(100vw, %dpx)',
            $breite($zeilenhoehe, self::RESERVE),
            $breite($zeilenhoeheMobil, self::RESERVE_MOBIL_HOHE_DICHTE),
            $breite($zeilenhoeheMobil, self::RESERVE_MOBIL),
        );
    }
}
