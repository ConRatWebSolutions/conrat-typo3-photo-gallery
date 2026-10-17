<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;

return [
    // Modul-Icon im Stil der TYPO3-14-Modulicons (currentColor + Akzentfarbe); als Sprite eingebunden,
    // damit es die Farben des Modulmenüs (hell/dunkel) übernimmt.
    'crgalerie-modul' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:crgalerie/Resources/Public/Icons/modul-sprite.svg#crgalerie-modul',
        'source' => 'EXT:crgalerie/Resources/Public/Icons/user_mod_crgalerie.svg',
    ],
    // Inhaltselement „Galerie“ (Bezeichner aus 3.0.0 beibehalten)
    'crgalerie-plugin' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:crgalerie/Resources/Public/Icons/user_plugin_crgalerie.svg',
    ],
    // Inhaltselement „Galerie-Übersicht“
    'crgalerie-uebersicht' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:crgalerie/Resources/Public/Icons/user_plugin_crgalerie_uebersicht.svg',
    ],
];
