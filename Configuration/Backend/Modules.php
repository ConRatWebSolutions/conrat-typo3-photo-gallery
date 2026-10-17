<?php

declare(strict_types=1);

use Conrat\Crgalerie\Controller\Backend\AdminController;

/**
 * Backend module configuration for crgalerie
 */
return [
    'web_crgalerie' => [
        // TYPO3 14: Hauptmodul „Web“ heißt jetzt „Content“ (Feature #107628), „List“ heißt „Records“.
        // Hinter „Records“; EXT:viewpage hängt „Preview“ ebenfalls dort an und wird früher geladen, steht also davor.
        // Ein zusätzliches 'before' => 'page_preview' löst die Modulsortierung nicht auf (Modul landet am Ende).
        'parent' => 'content',
        'position' => ['after' => 'records'],
        'access' => 'user',
        'iconIdentifier' => 'crgalerie-modul',
        // Bezeichner und Pfad bleiben wie in 3.0.0, damit Lesezeichen, Rechte (be_groups.groupMods) und die
        // AJAX-Route (inheritAccessFromModule) weiter passen.
        'path' => '/module/web/crgalerie',
        'labels' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Crgalerie',
        // Galerien hängen nicht an Seiten – kein Seitenbaum nötig
        'inheritNavigationComponentFromMainModule' => false,
        'controllerActions' => [
            AdminController::class => [
                'index', 'upload', 'deleteImage', 'sortImages', 'sortGalleries', 'toggleImageHidden', 'toggleGalleryHidden'
            ],
        ],
    ],
];
