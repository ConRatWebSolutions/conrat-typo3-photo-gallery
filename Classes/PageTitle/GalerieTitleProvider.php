<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\PageTitle;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

/**
 * Seitentitel bei der Anzeige einer Galerie (Muster aus der Site Setting crgalerie.pageTitle).
 */
final class GalerieTitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
