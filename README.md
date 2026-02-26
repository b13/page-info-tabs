# Page Info Tabs for TYPO3

Organizes page module header sections into Bootstrap 5 tabs when multiple
extensions contribute content. Renders directly (no tabs) when only one
source is present.

## The Problem

Multiple extensions inject HTML into the page module header via
`ModifyPageLayoutContentEvent::addHeaderContent()` — view statistics, blog
metadata, AI bot stats, etc. With two or more extensions active the header
becomes an unstructured stack.

## How It Works

1. Extensions that know about `page-info-tabs` listen to
   `CollectPageInfoSectionsEvent` and register `Section` objects (label,
   HTML content, priority).
2. Extensions that **don't** know about it keep using `addHeaderContent()`
   as before — their output is captured automatically as a fallback section.
3. The coordinator renders the result:
   - **0 sections** — nothing
   - **1 section** — rendered directly, no tabs
   - **2+ sections** — Bootstrap 5 tabs sorted by priority

No additional JavaScript is needed for tab switching — the TYPO3 backend
already loads Bootstrap's tab JS. A small resize-event helper is included
to fix chart libraries (Frappe Charts, Chart.js, etc.) that render
incorrectly inside initially hidden tab panes.

## Installation

```bash
composer require b13/page-info-tabs
```

## Registering a Section

Listen to `CollectPageInfoSectionsEvent` from your extension:

```php
use B13\PageInfoTabs\Event\CollectPageInfoSectionsEvent;
use B13\PageInfoTabs\Model\Section;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class MyPageInfoSection
{
    public function __invoke(CollectPageInfoSectionsEvent $event): void
    {
        $pageId = (int)($event->getRequest()->getQueryParams()['id'] ?? 0);

        $event->addSection(new Section(
            identifier: 'my-extension',
            label: 'My Label',
            content: '<div>Your HTML here</div>',
            priority: 50, // lower = leftmost tab
        ));
    }
}
```

If your extension should also work **without** `page-info-tabs` installed,
add a guard to your existing `ModifyPageLayoutContentEvent` listener:

```php
if (class_exists(\B13\PageInfoTabs\Event\CollectPageInfoSectionsEvent::class)) {
    return;
}
```

## Tab Ordering

| Priority | Typical Use |
|---|---|
| 10 | Static metadata (blog info, SEO) |
| 20 | Primary analytics (page views) |
| 30 | Specialized analytics (AI bots) |
| 100 | Legacy fallback (unstructured content) |

## Requirements

- PHP 8.2+
- TYPO3 13.4+ or 14.x

## License

GPL-2.0-or-later — see the [LICENSE](LICENSE) file for details.

## Background, authors & maintenance

This extension was created by [b13 GmbH, Stuttgart](https://b13.com).

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of our work,
we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
