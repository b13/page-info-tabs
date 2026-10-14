<?php

declare(strict_types=1);

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels'] = array_replace(
    [
        'view_tracker' => 'LLL:EXT:page_info_tabs/Resources/Private/Language/locallang.xlf:tab.view_tracker',
        'blog' => 'LLL:EXT:page_info_tabs/Resources/Private/Language/locallang.xlf:tab.blog',
    ],
    (array)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels'] ?? []),
);
