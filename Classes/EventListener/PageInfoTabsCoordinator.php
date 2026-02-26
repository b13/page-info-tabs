<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\EventListener;

use B13\PageInfoTabs\Event\CollectPageInfoSectionsEvent;
use B13\PageInfoTabs\Model\Section;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Fluid\View\TemplatePaths;

#[AsEventListener(
    identifier: 'b13/page-info-tabs/coordinator',
    after: 'my-extension/backend/modify-page-module-content, t3g/blog/modify-page-module-content',
)]
final class PageInfoTabsCoordinator
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        // Capture any legacy content that earlier listeners added
        $legacyContent = trim($event->getHeaderContent());
        // Clear it — we control the final output
        $event->setHeaderContent('');

        // Dispatch our structured event so aware extensions can register sections
        $collectEvent = new CollectPageInfoSectionsEvent($event->getRequest());
        $this->eventDispatcher->dispatch($collectEvent);
        $sections = $collectEvent->getSections();

        // Wrap legacy content as a fallback section
        if ($legacyContent !== '') {
            $sections[] = new Section(
                identifier: '_legacy',
                label: 'Info',
                content: $legacyContent,
                priority: 100,
            );
        }

        if ($sections === []) {
            return;
        }

        // Sort by priority (ascending — lower number = leftmost tab)
        usort($sections, static fn(Section $a, Section $b) => $a->priority <=> $b->priority);

        if (count($sections) === 1) {
            $event->setHeaderContent($sections[0]->content);
            return;
        }

        // 2+ sections: render as Bootstrap 5 tabs
        $this->pageRenderer->loadJavaScriptModule('@b13/page-info-tabs/tab-resize.js');

        $view = $this->viewFactory->create(new ViewFactoryData());
        if (!$view instanceof FluidViewAdapter) {
            // Fallback: concatenate all sections
            $event->setHeaderContent(implode('', array_map(static fn(Section $s) => $s->content, $sections)));
            return;
        }
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(['EXT:page_info_tabs/Resources/Private/Templates/PageInfoTabs']);
        $view->getRenderingContext()->setTemplatePaths($templatePaths);
        $view->assignMultiple([
            'sections' => $sections,
        ]);

        $event->setHeaderContent($view->render('Tabs'));
    }
}
