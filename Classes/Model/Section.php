<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "page_info_tabs" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\PageInfoTabs\Model;

final class Section
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $label,
        public readonly string $content,
        public readonly int $priority = 50,
        public readonly string $iconIdentifier = '',
    ) {}
}
