<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

/**
 * Was beim Löschen eines Bildes mit seiner Datei passiert ist (der Bild-Datensatz ist in jedem Fall gelöscht).
 */
enum LoeschErgebnis
{
    /** Die Datei wurde vom Server gelöscht. */
    case DateiGeloescht;
    /** Die Datei wird noch an anderer Stelle verwendet und bleibt erhalten. */
    case DateiWirdVerwendet;
    /** Die Datei wird nicht mehr verwendet, konnte aber nicht gelöscht werden (z. B. fehlende Rechte). */
    case DateiNichtGeloescht;
    /** Das Bild hatte keine Datei. */
    case KeineDatei;
}
