# Changelog

All notable changes to `b13/page-info-tabs` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Supported versions are TYPO3 13.4 LTS and 14.3 LTS.** The composer
  constraints no longer allow TYPO3 13.0–13.3, which lack APIs the extension
  relies on.

### Documentation
- **README covers development and output escaping.** It points to
  `config/system/additional.php` for configuration and states that section
  content is rendered as is.

### Tests
- **The unit test suite runs again** and is checked in CI together with
  coding standards and PHPStan on TYPO3 13.4 and 14.3.

## [1.0.1] and earlier

Initial releases. See the [README](README.md) for features and configuration.
