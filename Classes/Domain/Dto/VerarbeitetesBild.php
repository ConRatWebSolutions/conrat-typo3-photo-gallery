<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Dto;

/**
 * Fertig verarbeitetes Bild in einer temporären Datei, bereit für die Ablage in FAL.
 */
final readonly class VerarbeitetesBild
{
    public function __construct(
        public string $pfad,
        public string $dateiname,
        public int $breite,
        public int $hoehe,
    ) {}
}
