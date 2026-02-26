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
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Fluid\View\TemplatePaths;

final class TrackingEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $inner,
        private readonly ListenerProvider $listenerProvider,
        private readonly ContainerInterface $container,
        private readonly LabelResolver $labelResolver,
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
        $previousContent = '';
        $capturedSections = [];

        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);

            $currentContent = $event->getHeaderContent();
            if ($currentContent === $previousContent) {
                continue;
            }

            if (str_starts_with($currentContent, $previousContent)) {
                $delta = substr($currentContent, strlen($previousContent));
            } else {
                // Listener used setHeaderContent — treat entire new value as its content
                $delta = $currentContent;
            }

            if (trim($delta) !== '') {
                $capturedSections[] = trim($delta);
            }

            $previousContent = $currentContent;
        }

        // Collect explicit sections from extensions that know about page-info-tabs
        $collectEvent = new CollectPageInfoSectionsEvent($event->getRequest());
        $this->inner->dispatch($collectEvent);
        $explicitSections = $collectEvent->getSections();

        // Build final section list: explicit sections first
        $allSections = [];
        $explicitIdentifiers = [];
        foreach ($explicitSections as $section) {
            $allSections[] = $section;
            $explicitIdentifiers[] = $section->identifier;
        }

        // Auto-captured sections (skip if the same extension registered an explicit section)
        $autoPriority = 50;
        foreach ($capturedSections as $content) {
            $extensionKey = $this->labelResolver->detectExtensionKey($content);
            if ($extensionKey !== null && in_array($extensionKey, $explicitIdentifiers, true)) {
                continue;
            }
            $identifier = $extensionKey ?? ('auto-' . count($allSections));
            $allSections[] = new Section(
                identifier: $identifier,
                label: $this->labelResolver->resolveLabel($extensionKey),
                content: $content,
                priority: $this->labelResolver->resolvePriority($extensionKey, $autoPriority),
            );
            $autoPriority++;
        }

        if ($allSections === []) {
            $event->setHeaderContent('');
            return $event;
        }

        usort($allSections, static fn(Section $a, Section $b) => $a->priority <=> $b->priority);

        if (count($allSections) === 1) {
            $event->setHeaderContent($allSections[0]->content);
            return $event;
        }

        // 2+ sections: render as tabs (resolve lazily to avoid circular DI during bootstrap)
        $this->container->get(PageRenderer::class)->loadJavaScriptModule('@b13/page-info-tabs/tab-resize.js');

        $viewFactory = $this->container->get(ViewFactoryInterface::class);
        $view = $viewFactory->create(new ViewFactoryData());
        if (!$view instanceof FluidViewAdapter) {
            $event->setHeaderContent(implode('', array_map(static fn(Section $s) => $s->content, $allSections)));
            return $event;
        }
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(['EXT:page_info_tabs/Resources/Private/Templates/PageInfoTabs']);
        $view->getRenderingContext()->setTemplatePaths($templatePaths);
        $view->assignMultiple(['sections' => $allSections]);

        $event->setHeaderContent($view->render('Tabs'));
        return $event;
    }
}
