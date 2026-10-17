<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Service;

/**
 * Fehler bei der Bildverarbeitung. Die Meldung für Redakteure steht übersetzt unter $sprachschluessel
 * (locallang.xlf), die Exception-Message ist der englische Text für Logs.
 */
final class BildVerarbeitungFehler extends \RuntimeException
{
    public function __construct(
        public readonly string $sprachschluessel,
        public readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message, 1760600001);
    }
}
