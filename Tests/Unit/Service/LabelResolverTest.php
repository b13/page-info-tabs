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
use PHPUnit\Framework\TestCase;

final class LabelResolverTest extends LabelResolverTestCase
{
    public function testPlainStringPassesThrough(): void
    {
        $resolver = $this->makeResolver(static fn(string $key): ?string => 'should not be called');

        self::assertSame('Page Views', $resolver->resolveTranslation('Page Views'));
        self::assertSame('', $resolver->resolveTranslation(''));
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

abstract class LabelResolverTestCase extends TestCase
{
    /**
     * @param callable(string): ?string $translator
     */
    protected function makeResolver(callable $translator): LabelResolver
    {
        return new class($translator) extends LabelResolver {
            /** @var callable(string): ?string */
            private $translator;

            public function __construct(callable $translator)
            {
                $this->translator = $translator;
            }

            protected function translate(string $key): ?string
            {
                return ($this->translator)($key);
            }
        };
    }
}
