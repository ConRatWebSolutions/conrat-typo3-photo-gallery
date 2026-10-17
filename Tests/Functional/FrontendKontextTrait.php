<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional;

use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Extbase-Repositories brauchen einen Frontend-Request, damit Hidden/Deleted-Regeln greifen.
 */
trait FrontendKontextTrait
{
    private function richteFrontendKontextEin(): void
    {
        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupArray([]);
        $request = (new ServerRequest('https://website.local/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $typoScript);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $this->get(ConfigurationManagerInterface::class)->setRequest($request);
    }
}
