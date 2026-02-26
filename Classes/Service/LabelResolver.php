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
    /** @var array<string, string> */
    private readonly array $labels;

    /** @var array<string, int> */
    private readonly array $priorities;

    public function __construct()
    {
        $config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs'] ?? [];
        $this->labels = $config['labels'] ?? [];
        $this->priorities = $config['priorities'] ?? [];
    }

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

        // data-extension attribute
        if (preg_match('/data-extension=["\']([a-z_-]+)["\']/i', $content, $m)) {
            return $this->normalizeKey($m[1]);
        }

        return null;
    }

    public function resolveLabel(?string $extensionKey): string
    {
        if ($extensionKey !== null && isset($this->labels[$extensionKey])) {
            return $this->labels[$extensionKey];
        }
        if ($extensionKey !== null) {
            return ucwords(str_replace('_', ' ', $extensionKey));
        }
        return 'Info';
    }

    public function resolvePriority(?string $extensionKey, int $default): int
    {
        if ($extensionKey !== null && isset($this->priorities[$extensionKey])) {
            return $this->priorities[$extensionKey];
        }
        return $default;
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
