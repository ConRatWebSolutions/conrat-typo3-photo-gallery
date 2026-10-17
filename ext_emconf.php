<?php

// Nur noch für TER und Tailor: TYPO3 14.3 liest die Metadaten aus composer.json (Deprecation #108345),
// ab TYPO3 15 wird diese Datei ignoriert. Version muss mit extra.typo3/cms.version übereinstimmen.
$EM_CONF[$_EXTKEY] = [
    'title' => 'ConRat Photo Gallery',
    'description' => 'Bildergalerien mit zentraler Pflege im Backend-Modul: Drag-and-drop-Upload, Sortierung, responsive Vorschaubilder und barrierearme Lightbox.',
    'category' => 'plugin',
    'author' => 'ConRat WebSolutions',
    'author_email' => 'info@conrat.de',
    'author_company' => 'ConRat WebSolutions',
    'state' => 'stable',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '14.3.0-14.3.99',
            'backend' => '14.3.0-14.3.99',
            'extbase' => '14.3.0-14.3.99',
            'fluid' => '14.3.0-14.3.99',
            'frontend' => '14.3.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'filemetadata' => '14.3.0-14.3.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Conrat\\Crgalerie\\' => 'Classes/',
        ],
    ],
];
