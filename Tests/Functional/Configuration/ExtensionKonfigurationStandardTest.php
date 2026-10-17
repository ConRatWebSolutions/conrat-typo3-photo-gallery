<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Configuration;

use Conrat\Crgalerie\Configuration\ExtensionKonfiguration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExtensionKonfigurationStandardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'crgalerie' => [
                'uploadFolder' => 'kein gültiger Ordner',
                'maxBildgroesse' => '1600',
            ],
        ],
    ];

    #[Test]
    public function ungueltigerOrdnerErgibtStandardUndGueltigeGroesseBleibt(): void
    {
        $konfiguration = $this->get(ExtensionKonfiguration::class);
        self::assertSame('1:/galerien/', $konfiguration->getUploadFolder());
        self::assertSame(1600, $konfiguration->getMaxBildgroesse());
    }
}
