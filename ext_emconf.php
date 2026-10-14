<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Page Info Tabs',
    'description' => 'Organizes page module header sections into Bootstrap 5 tabs',
    'category' => 'be',
    'author' => 'b13 GmbH',
    'author_email' => 'typo3@b13.com',
    'author_company' => 'b13 GmbH',
    'state' => 'stable',
    'version' => '1.0.2',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
