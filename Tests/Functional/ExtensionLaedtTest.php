<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExtensionLaedtTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['conratwebsolutions/crgalerie'];

    #[Test]
    public function extensionIstGeladenUndTabellenExistieren(): void
    {
        self::assertTrue(ExtensionManagementUtility::isLoaded('crgalerie'));
        $schemaManager = $this->getConnectionPool()
            ->getConnectionForTable('tx_crgalerie_domain_model_galerie')
            ->createSchemaManager();
        self::assertTrue($schemaManager->tablesExist(['tx_crgalerie_domain_model_galerie']));
    }
}
