<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional\Configuration;

use Conrat\Crgalerie\Configuration\ExtensionKonfiguration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExtensionKonfigurationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['conrat/crgalerie'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'crgalerie' => [
                'storagePid' => '12',
                'previewPid' => 'keine Zahl',
                'uploadFolder' => '2:/bilder/galerien',
                'maxBildgroesse' => '99999',
            ],
        ],
    ];

    #[Test]
    public function liefertWerteAlsInteger(): void
    {
        $konfiguration = $this->get(ExtensionKonfiguration::class);
        self::assertSame(12, $konfiguration->getStoragePid());
        self::assertSame(0, $konfiguration->getPreviewPid());
    }

    #[Test]
    public function uploadFolderBekommtAbschliessendenSchraegstrich(): void
    {
        self::assertSame('2:/bilder/galerien/', $this->get(ExtensionKonfiguration::class)->getUploadFolder());
    }

    #[Test]
    public function maxBildgroesseAusserhalbDesBereichsErgibtStandard(): void
    {
        self::assertSame(2560, $this->get(ExtensionKonfiguration::class)->getMaxBildgroesse());
    }
}
