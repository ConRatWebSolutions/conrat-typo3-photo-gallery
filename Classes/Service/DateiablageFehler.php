<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

/**
 * Die Galerie-Ablage ist nicht nutzbar (z. B. Speicher aus der Extension-Konfiguration fehlt).
 * Redakteure sehen die übersetzte Meldung error.uploadFolder, die Exception-Message (englisch) steht im Log.
 */
final class DateiablageFehler extends \RuntimeException {}
