# Changelog

All notable changes to `b13/page-info-tabs` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] — 2026-10-14

### Added
- **Available in the TYPO3 Extension Repository** as `page_info_tabs`.
- **Tab labels are translatable.** The fallback label "Info" and the default
  labels for `blog` and `view_tracker` come from a language file with English
  and German labels. Configured labels are translated into the language of the
  backend user.

### Changed
- **The tab bar is rendered with the current request.** Fluid view helpers
  that need a request now work in the template, and the extension no longer
  depends on internal TYPO3 APIs for rendering and listener lookup.
- **Supported versions are TYPO3 13.4 LTS and 14.3 LTS.** The composer
  constraints no longer allow TYPO3 13.0–13.3, which lack APIs the extension
  relies on.

### Removed
- **`EXT:` references are no longer used for detection.** TYPO3 13 and 14
  render resources as `/_assets/…` URLs, so the pattern never matched.
- **Classes ending in `-header`, `-module`, or `-widget` are no longer used
  for detection**, only `-pageheader`. Add a `data-extension` attribute if
  your markup relied on them.

### Fixed
- **Generic markup is no longer taken for an extension.** Classes like
  `card-header` or `modal-header` produced tabs labeled "Card" or "Modal".
  Detected keys now have to name an active extension, and dashed keys like
  `tx-view-tracker` resolve to `view_tracker`.
- **Tabs stay unique.** Two sections with the same key, or identifiers with
  characters like `:` or `.`, produced duplicate or invalid DOM ids, so a tab
  opened the wrong panel or the tab script failed.
- **Explicit sections replace auto-captured content reliably.** An explicit
  identifier written with dashes (`my-extension`) now matches the detected
  extension key (`my_extension`), so the content no longer shows up twice.
- **The last used tab is restored in TYPO3 14.2 and later.** The new backend
  tab component replaces the Bootstrap attributes the script relied on, so the
  remembered tab was not selected anymore.
- **Tab markup follows the ARIA tabs pattern** before any script runs: tabs
  carry `aria-selected`, `tabindex`, and an id, panels `aria-labelledby`, and
  the wrapper no longer claims to be a tab panel itself.
- **Invalid label or priority configuration no longer breaks the page
  module.** Values of the wrong type are ignored and the default is used.
- **Listeners that rearrange the header content are handled.** Content that
  a listener prepends becomes its own tab instead of duplicating the earlier
  tabs, and a listener that replaces or clears the content is respected.

### Documentation
- **README covers development and output escaping.** It points to
  `config/system/additional.php` for configuration and states that section
  content is rendered as is.

### Tests
- **The unit test suite runs again** and is checked in CI together with
  coding standards and PHPStan on TYPO3 13.4 and 14.3.

## [1.0.1] and earlier

Initial releases. See the [README](README.md) for features and configuration.
