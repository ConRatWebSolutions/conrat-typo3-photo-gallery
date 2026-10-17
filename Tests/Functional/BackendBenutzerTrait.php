<?php

declare(strict_types=1);

namespace Conrat\Crgalerie\Tests\Functional;

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Meldet einen Backend-Benutzer an und stellt den Sprachservice bereit, den DataHandler und FAL brauchen.
 * Standardsprache Deutsch, damit die Tests auch die Übersetzungen (de.locallang.xlf) prüfen; 'default' = Englisch.
 */
trait BackendBenutzerTrait
{
    private function meldeAn(int $benutzerUid, string $sprache = 'de'): void
    {
        $benutzer = $this->setUpBackendUser($benutzerUid);
        $benutzer->user['lang'] = $sprache;
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($benutzer);
    }
}
