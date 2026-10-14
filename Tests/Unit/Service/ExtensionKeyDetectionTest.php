<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\Tests\Unit\Service;

use B13\PageInfoTabs\Service\LabelResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Package\PackageManager;

final class ExtensionKeyDetectionTest extends TestCase
{
    private const ACTIVE_EXTENSIONS = ['blog', 'news', 't3monitoring', 'view_tracker'];

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function contentDataProvider(): iterable
    {
        yield 'data-extension attribute' => ['<div data-extension="my_ext">', 'my_ext'];
        yield 'data-extension with dash is normalized' => ['<div data-extension="my-ext">', 'my_ext'];
        yield 'data-extension with digits' => ['<div data-extension="t3monitoring">', 't3monitoring'];
        yield 'data-extension wins over other patterns' => ['<div class="tx-news" data-extension="blog">', 'blog'];
        yield 'tx- class' => ['<div class="tx-news">', 'news'];
        yield 'tx_ class with plugin suffix' => ['<div class="tx_news_pi1">', 'news'];
        yield 'tx- class with dashed key' => ['<div class="tx-view-tracker">', 'view_tracker'];
        yield 'tx- class with dashed key and suffix' => ['<div class="tx-view-tracker-list">', 'view_tracker'];
        yield 'tx- id' => ['<section id="tx-blog">', 'blog'];
        yield 'tx- class among other classes' => ['<div class="card tx-news mb-3">', 'news'];
        yield 'b13 JS hook' => ['<div class="bJS_txViewTracker_chart">', 'view_tracker'];
        yield 'page header class' => ['<div class="blog-pageheader">', 'blog'];
        yield 'Bootstrap card header inside an extension' => ['<div class="tx-news"><div class="card"><div class="card-header">', 'news'];
        yield 'Bootstrap card header alone' => ['<div class="card"><div class="card-header">', null];
        yield 'modal header' => ['<div class="modal-header">', null];
        yield 'callout header' => ['<div class="callout-header">', null];
        yield 'inactive extension' => ['<div class="tx-unknown">', null];
        yield 'EXT: reference is not a pattern' => ['<img src="EXT:blog/Resources/Public/Icons/Extension.svg">', null];
        yield 'plain text' => ['<p>No markers here</p>', null];
    }

    #[Test]
    #[DataProvider('contentDataProvider')]
    public function detectsExtensionKey(string $content, ?string $expected): void
    {
        self::assertSame($expected, $this->createResolver()->detectExtensionKey($content));
    }

    #[Test]
    public function normalizesIdentifiers(): void
    {
        $resolver = $this->createResolver();

        self::assertSame('my_extension', $resolver->normalizeIdentifier('my-extension'));
        self::assertSame('my_extension', $resolver->normalizeIdentifier('My_Extension'));
    }

    private function createResolver(): LabelResolver
    {
        $packageManager = self::createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturnCallback(
            static fn(string $key): bool => in_array($key, self::ACTIVE_EXTENSIONS, true),
        );
        return new LabelResolver($packageManager, self::createStub(LanguageServiceFactory::class));
    }
}
