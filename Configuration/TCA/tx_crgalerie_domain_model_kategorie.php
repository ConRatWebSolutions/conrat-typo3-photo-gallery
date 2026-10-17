<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_kategorie',
        'label' => 'titel',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:crgalerie/Resources/Public/Icons/tx_crgalerie_domain_model_kategorie.gif',
        'rootLevel' => -1,
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, titel'],
    ],
    'columns' => [
        'titel' => [
            'label' => 'LLL:EXT:crgalerie/Resources/Private/Language/locallang_db.xlf:tx_crgalerie_domain_model_kategorie.titel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
];
