<?php

declare(strict_types=1);

use Conrat\Crgalerie\Controller\GalerieController;
use Conrat\Crgalerie\Controller\UebersichtController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

ExtensionUtility::configurePlugin(
    'Crgalerie',
    'Galerie',
    [GalerieController::class => 'show'],
);

ExtensionUtility::configurePlugin(
    'Crgalerie',
    'Uebersicht',
    [UebersichtController::class => 'list, show'],
);
