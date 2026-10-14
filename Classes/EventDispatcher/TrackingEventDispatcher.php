<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\EventDispatcher;

use B13\PageInfoTabs\Event\CollectPageInfoSectionsEvent;
use B13\PageInfoTabs\Model\Section;
use B13\PageInfoTabs\Service\LabelResolver;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Decorates the event dispatcher to see which listener added which part of
 * the page module header. PSR-14 has no hook for that, so listeners of
 * ModifyPageLayoutContentEvent are called here one by one instead of by the
 * decorated dispatcher. All other events are passed through untouched.
 * The event is final and not stoppable, so every listener is called.
 */
final class TrackingEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $inner,
        private readonly ListenerProviderInterface $listenerProvider,
        private readonly LabelResolver $labelResolver,
        // Lazy: both depend on the event dispatcher themselves
        #[Autowire(lazy: true)]
        private readonly PageRenderer $pageRenderer,
        #[Autowire(lazy: true)]
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function dispatch(object $event): object
    {
        if (!$event instanceof ModifyPageLayoutContentEvent) {
            return $this->inner->dispatch($event);
        }

        return $this->dispatchPageLayoutEvent($event);
    }

    private function dispatchPageLayoutEvent(ModifyPageLayoutContentEvent $event): ModifyPageLayoutContentEvent
    {
        $sections = $this->buildSections($event, $this->captureListenerContent($event));

        if ($sections === []) {
            $event->setHeaderContent('');
            return $event;
        }

        if (count($sections) === 1) {
            $event->setHeaderContent($sections[0]->content);
            return $event;
        }

        $this->pageRenderer->loadJavaScriptModule('@b13/page-info-tabs/tab-resize.js');
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:page_info_tabs/Resources/Private/Templates'],
            request: $event->getRequest(),
        ));
        $view->assign('tabs', $this->buildTabs($sections));
        $event->setHeaderContent($view->render('PageInfoTabs/Tabs'));
        return $event;
    }

    /**
     * Call each listener and record what it changed in the header content.
     * Appended and prepended content becomes a section of its own. A listener
     * that replaces the content (setHeaderContent) discards what was recorded
     * before, as the previous content is gone from the page as well.
     *
     * @return list<string>
     */
    private function captureListenerContent(ModifyPageLayoutContentEvent $event): array
    {
        $previousContent = $event->getHeaderContent();
        $captured = [];

        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);

            $currentContent = $event->getHeaderContent();
            if ($currentContent === $previousContent) {
                continue;
            }

            if (str_starts_with($currentContent, $previousContent)) {
                $captured[] = substr($currentContent, strlen($previousContent));
            } elseif (str_ends_with($currentContent, $previousContent)) {
                array_unshift($captured, substr($currentContent, 0, -strlen($previousContent)));
            } else {
                $captured = [$currentContent];
            }

            $previousContent = $currentContent;
        }

        return array_values(array_filter(
            array_map(trim(...), $captured),
            static fn(string $content): bool => $content !== '',
        ));
    }

    /**
     * Merge explicitly registered sections with the captured listener content.
     * An explicit section replaces the captured content of the same extension.
     *
     * @param list<string> $capturedContent
     * @return list<Section>
     */
    private function buildSections(ModifyPageLayoutContentEvent $event, array $capturedContent): array
    {
        $collectEvent = new CollectPageInfoSectionsEvent($event->getRequest());
        $this->inner->dispatch($collectEvent);

        $sections = $collectEvent->getSections();
        $explicitIdentifiers = array_map(
            fn(Section $section): string => $this->labelResolver->normalizeIdentifier($section->identifier),
            $sections,
        );

        $autoPriority = 50;
        foreach ($capturedContent as $content) {
            $extensionKey = $this->labelResolver->detectExtensionKey($content);
            if ($extensionKey !== null && in_array($extensionKey, $explicitIdentifiers, true)) {
                continue;
            }
            $sections[] = new Section(
                identifier: $extensionKey ?? ('auto-' . count($sections)),
                label: $this->labelResolver->resolveLabel($extensionKey),
                content: $content,
                priority: $this->labelResolver->resolvePriority($extensionKey, $autoPriority),
            );
            $autoPriority++;
        }

        $sections = array_map(
            fn(Section $section): Section => new Section(
                identifier: $section->identifier,
                label: $this->labelResolver->resolveTranslation($section->label),
                content: $section->content,
                priority: $section->priority,
                iconIdentifier: $section->iconIdentifier,
            ),
            $sections,
        );
        usort($sections, static fn(Section $a, Section $b): int => $a->priority <=> $b->priority);

        return $sections;
    }

    /**
     * Pair each section with an id that is unique and safe to use in DOM ids
     * and CSS selectors. The section identifier itself stays untouched, it is
     * what the tab memory in the browser keys on.
     *
     * @param list<Section> $sections
     * @return list<array{domId: string, section: Section}>
     */
    private function buildTabs(array $sections): array
    {
        $tabs = [];
        $usedIds = [];
        foreach ($sections as $section) {
            $baseId = trim((string)preg_replace('/[^a-z0-9_-]+/', '-', strtolower($section->identifier)), '-');
            if ($baseId === '') {
                $baseId = 'section';
            }
            $domId = $baseId;
            for ($suffix = 2; isset($usedIds[$domId]); $suffix++) {
                $domId = $baseId . '-' . $suffix;
            }
            $usedIds[$domId] = true;
            $tabs[] = ['domId' => $domId, 'section' => $section];
        }
        return $tabs;
    }
}
