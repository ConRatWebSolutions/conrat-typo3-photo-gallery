<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Domain;

use Conrat\Crgalerie\Domain\Sortierung;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortierungTest extends TestCase
{
    /**
     * @return array<int|string, array{mixed, Sortierung}>
     */
    public static function settingWerte(): array
    {
        return [
            'fehlt' => [null, Sortierung::ManuellAufsteigend],
            'leer' => ['', Sortierung::ManuellAufsteigend],
            'unbekannt' => ['9', Sortierung::ManuellAufsteigend],
            'text' => ['abc', Sortierung::ManuellAufsteigend],
            '1' => ['1', Sortierung::ManuellAufsteigend],
            '2' => ['2', Sortierung::ManuellAbsteigend],
            '3' => [3, Sortierung::TitelAufsteigend],
            '4' => ['4', Sortierung::TitelAbsteigend],
        ];
    }

    #[Test]
    #[DataProvider('settingWerte')]
    public function ausSettingLiefertPassendeSortierung(mixed $wert, Sortierung $erwartet): void
    {
        self::assertSame($erwartet, Sortierung::ausSetting($wert));
    }
}
