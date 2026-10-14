<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\Event;

use B13\PageInfoTabs\Model\Section;
use Psr\Http\Message\ServerRequestInterface;

final class CollectPageInfoSectionsEvent
{
    /** @var array<string, Section> */
    private array $sections = [];

    public function __construct(
        private readonly ServerRequestInterface $request,
    ) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function addSection(Section $section): void
    {
        $this->sections[$section->identifier] = $section;
    }

    /**
     * @return list<Section>
     */
    public function getSections(): array
    {
        return array_values($this->sections);
    }
}
