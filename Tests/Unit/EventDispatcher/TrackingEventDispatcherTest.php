<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\Tests\Unit\EventDispatcher;

use B13\PageInfoTabs\Event\CollectPageInfoSectionsEvent;
use B13\PageInfoTabs\EventDispatcher\TrackingEventDispatcher;
use B13\PageInfoTabs\Model\Section;
use B13\PageInfoTabs\Service\LabelResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

final class TrackingEventDispatcherTest extends TestCase
{
    /** @var list<array{domId: string, section: Section}>|null */
    private ?array $renderedTabs = null;

    private ?ViewFactoryData $viewFactoryData = null;

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']);
        parent::tearDown();
    }

    #[Test]
    public function otherEventsArePassedToTheInnerDispatcher(): void
    {
        $event = new \stdClass();
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())->method('dispatch')->with($event)->willReturn($event);

        $dispatcher = $this->createDispatcher([], [], $inner);

        self::assertSame($event, $dispatcher->dispatch($event));
    }

    #[Test]
    public function noContentRendersNothing(): void
    {
        $event = $this->dispatch([static function (): void {}]);

        self::assertSame('', $event->getHeaderContent());
        self::assertNull($this->renderedTabs);
    }

    #[Test]
    public function singleSectionIsRenderedWithoutTabs(): void
    {
        $event = $this->dispatch([
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
        ]);

        self::assertSame('<div class="tx-news">News</div>', $event->getHeaderContent());
        self::assertNull($this->renderedTabs);
    }

    #[Test]
    public function eachListenerBecomesATab(): void
    {
        $event = $this->dispatch([
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-blog">Blog</div>'),
        ]);

        self::assertSame('rendered tabs', $event->getHeaderContent());
        self::assertSame(['news', 'blog'], $this->renderedIdentifiers());
        self::assertSame(['News', 'Blog'], $this->renderedLabels());
        self::assertNotNull($this->viewFactoryData);
        self::assertSame(['EXT:page_info_tabs/Resources/Private/Templates'], $this->viewFactoryData->templateRootPaths);
        self::assertSame($event->getRequest(), $this->viewFactoryData->request);
    }

    #[Test]
    public function prependedContentBecomesItsOwnTab(): void
    {
        $this->dispatch([
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
            static fn(ModifyPageLayoutContentEvent $e) => $e->setHeaderContent('<div class="tx-blog">Blog</div>' . $e->getHeaderContent()),
        ]);

        self::assertSame(['blog', 'news'], $this->renderedIdentifiers());
        self::assertSame('<div class="tx-blog">Blog</div>', $this->renderedTabs[0]['section']->content ?? null);
    }

    #[Test]
    public function replacedContentDiscardsEarlierTabs(): void
    {
        $event = $this->dispatch([
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
            static fn(ModifyPageLayoutContentEvent $e) => $e->setHeaderContent('<div class="tx-blog">Blog</div>'),
        ]);

        self::assertSame('<div class="tx-blog">Blog</div>', $event->getHeaderContent());
    }

    #[Test]
    public function clearedContentRendersNothing(): void
    {
        $event = $this->dispatch([
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
            static fn(ModifyPageLayoutContentEvent $e) => $e->setHeaderContent(''),
        ]);

        self::assertSame('', $event->getHeaderContent());
    }

    #[Test]
    public function explicitSectionReplacesCapturedContentOfTheSameExtension(): void
    {
        $this->dispatch(
            [
                static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
                static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-view-tracker">Views</div>'),
            ],
            [new Section(identifier: 'view-tracker', label: 'Explicit views', content: 'explicit')],
        );

        self::assertSame(['view-tracker', 'news'], $this->renderedIdentifiers());
        self::assertSame('explicit', $this->renderedTabs[0]['section']->content ?? null);
    }

    #[Test]
    public function sectionsAreSortedByPriority(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['priorities']['blog'] = 10;

        $this->dispatch([
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News</div>'),
            static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-blog">Blog</div>'),
        ]);

        self::assertSame(['blog', 'news'], $this->renderedIdentifiers());
    }

    #[Test]
    public function domIdsAreUniqueAndSelectorSafe(): void
    {
        $this->dispatch(
            [
                static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div>first anonymous</div>'),
                static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News one</div>'),
                static fn(ModifyPageLayoutContentEvent $e) => $e->addHeaderContent('<div class="tx-news">News two</div>'),
            ],
            [
                new Section(identifier: 'auto-1', label: 'Explicit auto', content: 'explicit', priority: 1),
                new Section(identifier: 'my.ext:tab', label: 'Dotted', content: 'dotted', priority: 2),
            ],
        );

        $domIds = array_map(static fn(array $tab): string => $tab['domId'], $this->renderedTabs ?? []);
        self::assertSame(['auto-1', 'my-ext-tab', 'auto-2', 'news', 'news-2'], $domIds);
    }

    /**
     * @param list<callable(ModifyPageLayoutContentEvent): void> $listeners
     * @param list<Section> $explicitSections
     */
    private function dispatch(array $listeners, array $explicitSections = []): ModifyPageLayoutContentEvent
    {
        $event = new ModifyPageLayoutContentEvent(
            new ServerRequest('https://example.com/typo3/module/web/layout'),
            (new \ReflectionClass(ModuleTemplate::class))->newInstanceWithoutConstructor(),
        );
        $result = $this->createDispatcher($listeners, $explicitSections)->dispatch($event);
        self::assertSame($event, $result);
        return $event;
    }

    /**
     * @param list<callable(ModifyPageLayoutContentEvent): void> $listeners
     * @param list<Section> $explicitSections
     */
    private function createDispatcher(
        array $listeners,
        array $explicitSections,
        ?EventDispatcherInterface $inner = null,
    ): TrackingEventDispatcher {
        if ($inner === null) {
            $inner = self::createStub(EventDispatcherInterface::class);
            $inner->method('dispatch')->willReturnCallback(static function (object $event) use ($explicitSections): object {
                if ($event instanceof CollectPageInfoSectionsEvent) {
                    foreach ($explicitSections as $section) {
                        $event->addSection($section);
                    }
                }
                return $event;
            });
        }

        $listenerProvider = self::createStub(ListenerProviderInterface::class);
        $listenerProvider->method('getListenersForEvent')->willReturn($listeners);

        $packageManager = self::createStub(PackageManager::class);
        $packageManager->method('isPackageActive')->willReturnCallback(
            static fn(string $key): bool => in_array($key, ['blog', 'news', 'view_tracker'], true),
        );

        $view = self::createStub(ViewInterface::class);
        $view->method('assign')->willReturnCallback(function (string $key, mixed $value) use (&$view): ViewInterface {
            if ($key === 'tabs') {
                /** @var list<array{domId: string, section: Section}> $value */
                $this->renderedTabs = $value;
            }
            return $view;
        });
        $view->method('render')->willReturn('rendered tabs');

        $viewFactory = self::createStub(ViewFactoryInterface::class);
        $viewFactory->method('create')->willReturnCallback(function (ViewFactoryData $data) use ($view): ViewInterface {
            $this->viewFactoryData = $data;
            return $view;
        });

        return new TrackingEventDispatcher(
            $inner,
            $listenerProvider,
            new LabelResolver($packageManager),
            self::createStub(PageRenderer::class),
            $viewFactory,
        );
    }

    /**
     * @return list<string>
     */
    private function renderedIdentifiers(): array
    {
        return array_map(static fn(array $tab): string => $tab['section']->identifier, $this->renderedTabs ?? []);
    }

    /**
     * @return list<string>
     */
    private function renderedLabels(): array
    {
        return array_map(static fn(array $tab): string => $tab['section']->label, $this->renderedTabs ?? []);
    }
}
