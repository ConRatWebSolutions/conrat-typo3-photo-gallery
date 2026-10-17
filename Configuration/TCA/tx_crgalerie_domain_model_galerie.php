<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie',
        'label' => 'titel',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'default_sortby' => 'sortierung ASC, uid ASC',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'iconfile' => 'EXT:crgalerie/Resources/Public/Icons/tx_crgalerie_domain_model_galerie.gif',
        // Ebene 0 (Bestandsdaten) und Sysordner erlaubt
        'rootLevel' => -1,
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, titel, beschreibung, fotograf, aufnahmezeitpunkt, sortierung, kategorie, bilder, --div--;core.form.tabs:access, starttime, endtime'],
    ],
    'columns' => [
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'titel' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.titel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'beschreibung' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.beschreibung',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 8,
                'eval' => 'trim',
            ],
        ],
        'fotograf' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.fotograf',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'aufnahmezeitpunkt' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.aufnahmezeitpunkt',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'datetime',
                'nullable' => true,
            ],
        ],
        'sortierung' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.sortierung',
            'config' => [
                'type' => 'number',
                'size' => 5,
                'default' => 0,
            ],
        ],
        'kategorie' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.kategorie',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_crgalerie_domain_model_kategorie',
                'default' => 0,
            ],
        ],
        'bilder' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_galerie.bilder',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_crgalerie_domain_model_bilder',
                'foreign_field' => 'galerie',
                'foreign_sortby' => 'sortierung',
                'appearance' => [
                    'collapseAll' => true,
                    'useSortable' => true,
                ],
            ],
        ],
    ],
];
