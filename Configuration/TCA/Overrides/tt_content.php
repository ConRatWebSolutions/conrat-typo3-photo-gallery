<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

ExtensionUtility::registerPlugin(
    'Crgalerie',
    'Galerie',
    'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:plugin.galerie.title',
    'crgalerie-plugin',
    'plugins',
    'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:plugin.galerie.description',
    'FILE:EXT:crgalerie/Configuration/FlexForms/Galerie.xml',
);

ExtensionUtility::registerPlugin(
    'Crgalerie',
    'Uebersicht',
    'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:plugin.uebersicht.title',
    'crgalerie-uebersicht',
    'plugins',
    'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:plugin.uebersicht.description',
    'FILE:EXT:crgalerie/Configuration/FlexForms/Uebersicht.xml',
);
