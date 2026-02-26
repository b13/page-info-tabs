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

final class LabelResolver
{
    /**
     * Detect the TYPO3 extension key from rendered HTML content.
     *
     * Looks for common patterns:
     *  - CSS classes like "blog-pageheader", "tx-blog-*", "tx_blog_*"
     *  - JS hooks like "bJS_txViewTracker*"
     *  - EXT: references in URLs or attributes
     *  - data-extension attributes
     */
    public function detectExtensionKey(string $content): ?string
    {
        // Explicit data-extension attribute (highest priority)
        if (preg_match('/data-extension=["\']([a-z_-]+)["\']/i', $content, $m)) {
            return $this->normalizeKey($m[1]);
        }

        // Extension-specific CSS class prefixes: "blog-pageheader" → "blog"
        if (preg_match('/class=["\'][^"\']*\b([a-z][a-z0-9]*)-(?:pageheader|header|module|widget)\b/i', $content, $m)) {
            return $this->normalizeKey($m[1]);
        }

        // TYPO3 convention: "tx-ext-key" or "tx_ext_key" in class/id
        if (preg_match('/(?:class|id)=["\'][^"\']*\btx[-_]([a-z][a-z0-9_]+)/i', $content, $m)) {
            return $this->normalizeKey($m[1]);
        }

        // b13 JS hook convention: "bJS_txExtensionName"
        if (preg_match('/bJS_tx([A-Z][a-zA-Z]+)/', $content, $m)) {
            return $this->camelToSnake($m[1]);
        }

        // EXT: references in src/href attributes
        if (preg_match('/(?:src|href)=["\'][^"\']*EXT:([a-z_]+)\//i', $content, $m)) {
            return $m[1];
        }

        return null;
    }

    public function resolveLabel(?string $extensionKey): string
    {
        $labels = $this->getConfig()['labels'] ?? [];
        if ($extensionKey !== null && isset($labels[$extensionKey])) {
            return $labels[$extensionKey];
        }
        if ($extensionKey !== null) {
            return ucwords(str_replace('_', ' ', $extensionKey));
        }
        return 'Info';
    }

    public function resolvePriority(?string $extensionKey, int $default): int
    {
        $priorities = $this->getConfig()['priorities'] ?? [];
        if ($extensionKey !== null && isset($priorities[$extensionKey])) {
            return (int)$priorities[$extensionKey];
        }
        return $default;
    }

    /**
     * Read config at call time so ext_localconf.php values are available.
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs'] ?? [];
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(str_replace('-', '_', $key));
    }

    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
