<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain\Dto;

/**
 * Bildquellen für die Ausgabe: Vorschau mit srcset und große Fassung für die Lightbox.
 */
final readonly class BildQuellen
{
    public function __construct(
        public string $src,
        public string $srcset,
        public int $breite,
        public int $hoehe,
        public string $grossUrl,
        public int $grossBreite,
        public int $grossHoehe,
        public string $verhaeltnis,
    ) {}
}
