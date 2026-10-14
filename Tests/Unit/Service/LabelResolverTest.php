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

final class LabelResolverTest extends LabelResolverTestCase
{
    public function testPlainStringPassesThrough(): void
    {
        $resolver = $this->makeResolver(static fn(string $key): ?string => 'should not be called');

        self::assertSame('Page Views', $resolver->resolveTranslation('Page Views'));
        self::assertSame('', $resolver->resolveTranslation(''));
        // Colon alone is not a translation reference — must have a dotted domain prefix.
        self::assertSame('Page: 5', $resolver->resolveTranslation('Page: 5'));
        self::assertSame('Time 12:00', $resolver->resolveTranslation('Time 12:00'));
    }

    public function testV14DomainSyntaxIsTranslated(): void
    {
        $resolver = $this->makeResolver(static fn(string $key): ?string => match ($key) {
            'my_ext.tab:label' => 'Translated',
            'my_blog.frontend.forms:submit' => 'Submit',
            'my_extension.comment:domain_model.title' => 'Title',
            default => null,
        });

        self::assertSame('Translated', $resolver->resolveTranslation('my_ext.tab:label'));
        self::assertSame('Submit', $resolver->resolveTranslation('my_blog.frontend.forms:submit'));
        self::assertSame('Title', $resolver->resolveTranslation('my_extension.comment:domain_model.title'));
    }

    public function testTranslateExceptionsFallBackToRawLabel(): void
    {
        // v13 raises on v14 domain syntax; ensure rendering never fails.
        $resolver = $this->makeResolver(static function (string $key): ?string {
            throw new \InvalidArgumentException('unknown extension');
        });

        self::assertSame('my_ext.tab:label', $resolver->resolveTranslation('my_ext.tab:label'));
    }

    public function testLllKeyReturnsTranslation(): void
    {
        $resolver = $this->makeResolver(static fn(string $key): ?string => match ($key) {
            'LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:tab.label' => 'Translated Label',
            default => null,
        });

        self::assertSame(
            'Translated Label',
            $resolver->resolveTranslation('LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:tab.label'),
        );
    }

    public function testMissingTranslationFallsBackToRawLabel(): void
    {
        $nullResolver = $this->makeResolver(static fn(string $key): ?string => null);
        $emptyResolver = $this->makeResolver(static fn(string $key): ?string => '');

        $key = 'LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:missing';
        self::assertSame($key, $nullResolver->resolveTranslation($key));
        self::assertSame($key, $emptyResolver->resolveTranslation($key));
    }
}
