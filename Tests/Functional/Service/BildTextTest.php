<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Service;

use Conrat\Crgalerie\Service\BildAnzeigeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BildTextTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function randFaelle(): array
    {
        return [
            'Anführungszeichen bleiben' => ['„Burgtor“ im Abendlicht', '„Burgtor“ im Abendlicht'],
            'Typografische Zeichen am Ende' => ['Das ist ’ne Sache •', 'Das ist ’ne Sache •'],
            'Gedankenstriche entfernt' => ['– Titel –', 'Titel'],
            'Bindestrich und Leerraum' => [" - Titel -\n", 'Titel'],
        ];
    }

    #[Test]
    #[DataProvider('randFaelle')]
    public function trimmeRandIstUtf8Sicher(string $eingabe, string $erwartet): void
    {
        $ergebnis = BildAnzeigeService::trimmeRand($eingabe);
        self::assertSame($erwartet, $ergebnis);
        self::assertTrue(mb_check_encoding($ergebnis, 'UTF-8'));
    }

    /**
     * @return array<string, array{string, array{string, string}}>
     */
    public static function copyrightFaelle(): array
    {
        return [
            '(c)' => ['Text (c) Max Muster', ['Text', 'Max Muster']],
            '[C]' => ['Text [C] Max', ['Text', 'Max']],
            'Copyright-Zeichen' => ['Text © Anna', ['Text', 'Anna']],
            'ohne Copyright' => ['Ohne Copyright', ['Ohne Copyright', '']],
            'leer' => ['', ['', '']],
        ];
    }

    /**
     * @param array{string, string} $erwartet
     */
    #[Test]
    #[DataProvider('copyrightFaelle')]
    public function copyrightWirdGetrennt(string $eingabe, array $erwartet): void
    {
        self::assertSame($erwartet, BildAnzeigeService::trenneCopyright($eingabe));
    }
}
