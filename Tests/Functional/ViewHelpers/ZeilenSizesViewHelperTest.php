<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\ViewHelpers;

use Conrat\Crgalerie\ViewHelpers\ZeilenSizesViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

final class ZeilenSizesViewHelperTest extends TestCase
{
    /**
     * Desktop/Tablet: Verhältnis × 220 px × 1,4; Handy: × 100 px × 0,88 (ab DPR 2,5) bzw. × 1,33.
     *
     * @return array<string, array{0: float, 1: string}>
     */
    public static function verhaeltnisse(): array
    {
        return [
            'Querformat 3:2' => [1.5, self::erwartet(462, 132, 200)],
            'Querformat 4:3' => [1.3333, self::erwartet(411, 117, 177)],
            'Hochformat 3:4' => [0.75, self::erwartet(231, 66, 100)],
            'Panorama 3:1' => [3.0, self::erwartet(924, 264, 399)],
            'ungültig → 3:2' => [0.0, self::erwartet(462, 132, 200)],
        ];
    }

    private static function erwartet(int $desktop, int $mobilHoheDichte, int $mobil): string
    {
        return sprintf(
            '(min-width: 600px) min(100vw, %dpx), (min-resolution: 2.5dppx) min(100vw, %dpx), min(100vw, %dpx)',
            $desktop,
            $mobilHoheDichte,
            $mobil,
        );
    }

    #[Test]
    #[DataProvider('verhaeltnisse')]
    public function alleBreakpointsFolgenDemSeitenverhaeltnis(float $verhaeltnis, string $erwartet): void
    {
        self::assertSame($erwartet, ZeilenSizesViewHelper::sizes($verhaeltnis));
    }

    #[Test]
    public function eigeneZeilenhoehenSkalierenDieBreiten(): void
    {
        // 260 px ab 600 px, 120 px darunter
        self::assertSame(self::erwartet(546, 158, 239), ZeilenSizesViewHelper::sizes(1.5, 260, 120));
    }

    #[Test]
    public function ungueltigeZeilenhoehenErgebenStandard(): void
    {
        self::assertSame(ZeilenSizesViewHelper::sizes(1.5), ZeilenSizesViewHelper::sizes(1.5, 0, -5));
    }

    #[Test]
    public function viewHelperInFluid(): void
    {
        $view = new TemplateView();
        $view->getRenderingContext()->getViewHelperResolver()->addNamespace('crg', 'Conrat\\Crgalerie\\ViewHelpers');
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource(
            '{crg:zeilenSizes(verhaeltnis: \'0.75\')}|{crg:zeilenSizes(verhaeltnis: \'1.5\', zeilenhoehe: 260, zeilenhoeheMobil: 120)}'
        );
        self::assertSame(
            self::erwartet(231, 66, 100) . '|' . self::erwartet(546, 158, 239),
            $view->render()
        );
    }
}
