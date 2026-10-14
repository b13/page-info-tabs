<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()->exclude(['.Build', 'var'])->in(__DIR__ . '/..');
return $config;
