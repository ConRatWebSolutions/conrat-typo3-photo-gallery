<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_bilder',
        'label' => 'titel',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:crgalerie/Resources/Public/Icons/tx_crgalerie_domain_model_bilder.gif',
        'hideTable' => true,
        'rootLevel' => -1,
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, titel, bildunterschrift, datei, --div--;core.form.tabs:access, starttime, endtime'],
    ],
    'columns' => [
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'titel' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_bilder.titel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'bildunterschrift' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_bilder.bildunterschrift',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 4,
                'eval' => 'trim',
            ],
        ],
        'sortierung' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'datei' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_bilder.datei',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 1,
            ],
        ],
        'galerie' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
