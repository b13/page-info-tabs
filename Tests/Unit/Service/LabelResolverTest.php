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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Package\PackageManager;

final class LabelResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']);
        parent::tearDown();
    }

    #[Test]
    public function plainStringPassesThrough(): void
    {
        $resolver = $this->createResolver(static fn(string $key): string => 'should not be called');

        self::assertSame('Page Views', $resolver->resolveTranslation('Page Views'));
        self::assertSame('', $resolver->resolveTranslation(''));
        // Colon alone is not a translation reference — must have a dotted domain prefix.
        self::assertSame('Page: 5', $resolver->resolveTranslation('Page: 5'));
        self::assertSame('Time 12:00', $resolver->resolveTranslation('Time 12:00'));
    }

    #[Test]
    public function v14DomainSyntaxIsTranslated(): void
    {
        $resolver = $this->createResolver(static fn(string $key): string => match ($key) {
            'my_ext.tab:label' => 'Translated',
            'my_blog.frontend.forms:submit' => 'Submit',
            'my_extension.comment:domain_model.title' => 'Title',
            default => '',
        });

        self::assertSame('Translated', $resolver->resolveTranslation('my_ext.tab:label'));
        self::assertSame('Submit', $resolver->resolveTranslation('my_blog.frontend.forms:submit'));
        self::assertSame('Title', $resolver->resolveTranslation('my_extension.comment:domain_model.title'));
    }

    #[Test]
    public function translateExceptionsFallBackToRawLabel(): void
    {
        $resolver = $this->createResolver(static function (string $key): string {
            throw new \InvalidArgumentException('unknown extension', 1760438400);
        });

        self::assertSame('my_ext.tab:label', $resolver->resolveTranslation('my_ext.tab:label'));
    }

    #[Test]
    public function lllKeyReturnsTranslation(): void
    {
        $resolver = $this->createResolver(static fn(string $key): string => match ($key) {
            'LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:tab.label' => 'Translated Label',
            default => '',
        });

        self::assertSame(
            'Translated Label',
            $resolver->resolveTranslation('LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:tab.label'),
        );
    }

    #[Test]
    public function missingTranslationFallsBackToRawLabel(): void
    {
        $resolver = $this->createResolver(static fn(string $key): string => '');

        $key = 'LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:missing';
        self::assertSame($key, $resolver->resolveTranslation($key));
    }

    #[Test]
    public function labelWithoutExtensionKeyIsTranslatableFallback(): void
    {
        self::assertSame(
            'LLL:EXT:page_info_tabs/Resources/Private/Language/locallang.xlf:tab.fallback',
            $this->createResolver()->resolveLabel(null),
        );
    }

    #[Test]
    public function configuredLabelIsUsed(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels']['news'] = 'Latest news';

        self::assertSame('Latest news', $this->createResolver()->resolveLabel('news'));
    }

    #[Test]
    public function invalidConfiguredLabelFallsBackToExtensionKey(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels']['view_tracker'] = ['not', 'a', 'string'];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels']['blog'] = 42;

        $resolver = $this->createResolver();

        self::assertSame('View Tracker', $resolver->resolveLabel('view_tracker'));
        self::assertSame('Blog', $resolver->resolveLabel('blog'));
    }

    #[Test]
    public function brokenConfigurationIsIgnored(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels'] = 'broken';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['priorities'] = 'broken';

        $resolver = $this->createResolver();

        self::assertSame('News', $resolver->resolveLabel('news'));
        self::assertSame(50, $resolver->resolvePriority('news', 50));
    }

    #[Test]
    public function priorityAcceptsNumericValuesOnly(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['priorities'] = [
            'news' => '10',
            'blog' => 'first',
            'view_tracker' => 20,
        ];

        $resolver = $this->createResolver();

        self::assertSame(10, $resolver->resolvePriority('news', 50));
        self::assertSame(50, $resolver->resolvePriority('blog', 50));
        self::assertSame(20, $resolver->resolvePriority('view_tracker', 50));
        self::assertSame(50, $resolver->resolvePriority(null, 50));
    }

    /**
     * @param (callable(string): string)|null $translator
     */
    private function createResolver(?callable $translator = null): LabelResolver
    {
        $languageService = self::createStub(LanguageService::class);
        $languageService->method('sL')->willReturnCallback($translator ?? static fn(string $key): string => '');

        $languageServiceFactory = self::createStub(LanguageServiceFactory::class);
        $languageServiceFactory->method('createFromUserPreferences')->willReturn($languageService);

        return new LabelResolver(self::createStub(PackageManager::class), $languageServiceFactory);
    }
}
