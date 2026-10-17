<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Domain;

/**
 * Sortierarten der Bilder (Werte wie in der FlexForm „order“).
 */
enum Sortierung: int
{
    case ManuellAufsteigend = 1;
    case ManuellAbsteigend = 2;
    case TitelAufsteigend = 3;
    case TitelAbsteigend = 4;

    public static function ausSetting(mixed $wert): self
    {
        if (is_int($wert) || (is_string($wert) && ctype_digit($wert))) {
            return self::tryFrom((int)$wert) ?? self::ManuellAufsteigend;
        }
        return self::ManuellAufsteigend;
    }
}
