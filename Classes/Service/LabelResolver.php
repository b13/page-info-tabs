<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\Service;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Not final: the event dispatcher decorator gets it as a lazy proxy, as the
 * language service factory must not be built during ext_localconf.php loading.
 */
class LabelResolver
{
    private const FALLBACK_LABEL = 'LLL:EXT:page_info_tabs/Resources/Private/Language/locallang.xlf:tab.fallback';

    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * Detect the TYPO3 extension key from rendered HTML content.
     *
     * A `data-extension` attribute is taken as is. All other patterns only
     * yield candidates, and the first candidate that is an active extension
     * wins. This keeps generic markup like `card-header` from being mistaken
     * for an extension. Patterns, in order:
     *  - `data-extension="my_ext"`
     *  - TYPO3 convention classes/ids: `tx-my-ext-list`, `tx_my_ext_pi1`
     *  - b13 JS hooks: `bJS_txMyExt`
     *  - page header classes: `my-ext-pageheader`
     */
    public function detectExtensionKey(string $content): ?string
    {
        if (preg_match('/data-extension=["\']([a-z0-9_-]+)["\']/i', $content, $m)) {
            return $this->normalizeIdentifier($m[1]);
        }

        foreach ($this->collectCandidates($content) as $candidate) {
            if ($this->packageManager->isPackageActive($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Normalize an identifier the way extension keys are written,
     * so `my-extension` and `my_extension` compare equal.
     */
    public function normalizeIdentifier(string $identifier): string
    {
        return strtolower(str_replace('-', '_', $identifier));
    }

    /**
     * @return list<string>
     */
    private function collectCandidates(string $content): array
    {
        $tokens = [];
        if (preg_match_all('/\b(?:class|id)=["\']([^"\']*)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $attributeValue) {
                $tokens = [...$tokens, ...(preg_split('/\s+/', strtolower($attributeValue), -1, PREG_SPLIT_NO_EMPTY) ?: [])];
            }
        }

        $candidates = [];
        foreach ($tokens as $token) {
            if (preg_match('/^tx[-_]([a-z][a-z0-9_-]*)$/', $token, $m)) {
                $candidates = [...$candidates, ...$this->prefixCandidates($m[1])];
            }
        }
        if (preg_match_all('/bJS_tx([A-Z][a-zA-Z0-9]+)/', $content, $matches)) {
            foreach ($matches[1] as $name) {
                $candidates[] = GeneralUtility::camelCaseToLowerCaseUnderscored($name);
            }
        }
        foreach ($tokens as $token) {
            if (preg_match('/^([a-z][a-z0-9_-]*)-pageheader$/', $token, $m)) {
                $candidates = [...$candidates, ...$this->prefixCandidates($m[1])];
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * "view-tracker-list" → ["view_tracker_list", "view_tracker", "view"]
     *
     * @return list<string>
     */
    private function prefixCandidates(string $value): array
    {
        $parts = preg_split('/[-_]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidates = [];
        for ($length = count($parts); $length > 0; $length--) {
            $candidates[] = implode('_', array_slice($parts, 0, $length));
        }
        return $candidates;
    }

    public function resolveLabel(?string $extensionKey): string
    {
        if ($extensionKey === null) {
            return self::FALLBACK_LABEL;
        }
        $label = $this->getConfig('labels')[$extensionKey] ?? null;
        if (is_string($label) && $label !== '') {
            return $label;
        }
        return ucwords(str_replace('_', ' ', $extensionKey));
    }

    /**
     * Translate label references with the language of the backend user.
     * Recognises both the legacy `LLL:EXT:.../locallang.xlf:key` syntax and the
     * v14+ translation-domain syntax `package.resource:identifier`. Plain strings
     * pass through unchanged; missing translations fall back to the raw label so
     * a typo in a key never produces an empty tab.
     */
    public function resolveTranslation(string $label): string
    {
        if (!$this->looksLikeTranslationReference($label)) {
            return $label;
        }
        try {
            $translated = $this->translate($label);
        } catch (\Throwable) {
            // A broken label reference must never break the page module.
            return $label;
        }
        return $translated === '' ? $label : $translated;
    }

    private function translate(string $key): string
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        return $this->languageServiceFactory
            ->createFromUserPreferences($backendUser instanceof AbstractUserAuthentication ? $backendUser : null)
            ->sL($key);
    }

    /**
     * - Legacy: starts with `LLL:`
     * - v14 domain: `package.segment(.segment)*:identifier`, snake_case parts, no spaces.
     *   Requires at least one dot before the colon to keep plain labels like "Page: 5" out.
     */
    private function looksLikeTranslationReference(string $label): bool
    {
        if (str_starts_with($label, 'LLL:')) {
            return true;
        }
        return (bool)preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+:[A-Za-z0-9_.\-]+$/', $label);
    }

    public function resolvePriority(?string $extensionKey, int $default): int
    {
        $priority = $extensionKey !== null ? ($this->getConfig('priorities')[$extensionKey] ?? null) : null;
        return is_numeric($priority) ? (int)$priority : $default;
    }

    /**
     * Read config at call time so ext_localconf.php values are available.
     *
     * @return array<array-key, mixed>
     */
    private function getConfig(string $key): array
    {
        $config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs'][$key] ?? [];
        return is_array($config) ? $config : [];
    }
}
