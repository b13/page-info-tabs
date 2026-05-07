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

Page Info Tabs decorates TYPO3's `EventDispatcherInterface` and
automatically captures the content each `ModifyPageLayoutContentEvent`
listener adds. Every listener's output becomes its own tab — **no code
changes required** in any extension.

- **0 sections** — nothing rendered
- **1 section** — rendered directly, no tabs
- **2+ sections** — Bootstrap 5 tabs sorted by priority

Tab labels are auto-detected from HTML patterns (CSS classes like
`blog-pageheader`, `tx-ext-key`, `bJS_tx*`, `EXT:` references) and can be
configured explicitly.

No additional JavaScript is needed for tab switching — the TYPO3 backend
already loads Bootstrap's tab JS. A small resize-event helper is included
to fix chart libraries (Frappe Charts, Chart.js, etc.) that render
incorrectly inside initially hidden tab panes.

The most recently used tab is remembered in localStorage and automatically
selected when navigating between pages.

## Installation

```bash
composer require b13/page-info-tabs
```

That's it. All existing extensions that use `addHeaderContent()` will
automatically get their own tab.

## Configuring Labels

Default labels are provided for known extensions. To customise or add
labels, set them in your `ext_localconf.php` or `AdditionalConfiguration.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels']['view_tracker'] = 'Page Views';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels']['blog'] = 'Blog';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['labels']['my_extension'] = 'My Label';
```

Priorities (tab ordering) can be configured the same way:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['priorities']['view_tracker'] = 20;
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['page_info_tabs']['priorities']['blog'] = 10;
```

Lower priority = leftmost tab.

## Finding the Extension Key

Each tab panel includes an HTML comment with the detected key. Inspect
the tab content in your browser's developer tools to find it:

```html
<!-- page-info-tabs key: view_tracker -->
```

Use that key in your label and priority configuration. The key is
auto-detected from HTML patterns in the following order:

1. `data-extension="my_ext"` attribute (most reliable — extensions can
   add this explicitly)
2. CSS classes like `my-ext-pageheader` or `my-ext-widget`
3. TYPO3 convention classes: `tx-my-ext` or `tx_my_ext`
4. b13 JS hooks: `bJS_txMyExt`
5. `EXT:my_ext/` references in `src`/`href` attributes

If auto-detection produces an unexpected key, you can either configure a
label for that key directly, or ask the extension author to add a
`data-extension` attribute to their HTML output.

## Explicit Section Registration (Optional)

Extensions that want full control over their tab label, priority, and
content can listen to `CollectPageInfoSectionsEvent` instead. Explicit
sections take precedence over auto-captured content from the same
extension.

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
            priority: 50,
        ));
    }
}
```

`label` accepts either a plain string or an `LLL:` translation key —
e.g. `'LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:tab.label'`.
Keys are translated when the tab bar is rendered; if the key is missing
from the language file the raw label is used as a fallback.

## Requirements

- PHP 8.2+
- TYPO3 13.4+ or 14.x

## License

GPL-2.0-or-later — see the [LICENSE](LICENSE) file for details.

## Background, authors & maintenance

This extension was created in 2026 by David Steeb for [b13 GmbH, Stuttgart](https://b13.com).

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of our work,
we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
