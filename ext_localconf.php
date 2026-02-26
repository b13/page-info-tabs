<?php

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels'] = array_replace(
    [
        'view_tracker' => 'Page Views',
        'blog' => 'Blog',
    ],
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels'] ?? [],
);
