# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Bug fixes.

## [1.1.0] - 2026-11-06

### Added

- Laravel 13 support.
- Integration name and version sent with requests.

## [1.0.12] - 2026-03-16

### Changed

- Unified `SmsSender::send()` for single, multiple and bulk sends.
- SMS page calculator with encoding detection and cost estimate.
- Balance syncs from the API when stale.
- `SmsSent` and `SmsFailed` events.

### Added

- Recipient type and page count in send logs.

## [1.0.11] - 2026-03-15

### Added

- Auto-created GitHub Releases from tags.

## [1.0.10] - 2026-03-15

### Added

- Extra unit tests.

## [1.0.9] - 2026-03-13

### Changed

- Maintenance.

## [1.0.8] - 2026-03-13

### Fixed

- Bug fixes.

## [1.0.7] - 2026-03-13

### Added

- Country-specific phone format validation.

## [1.0.6] - 2026-03-13

### Fixed

- Bug fixes.

## [1.0.5] - 2026-03-13

### Fixed

- Bug fixes.

## [1.0.3] - 2026-03-13

### Fixed

- Bug fixes.

## [1.0.2] - 2026-03-13

### Changed

- README restructured.

## [1.0.1] - 2026-03-13

### Added

- GitHub Actions CI checks.

---

## [1.0.0] - 2026-03-13

### Added

- kwtSMS REST/JSON API client.
- Laravel Notification Channel.
- `SmsSender` send pipeline with bulk batching.
- Phone normalization and message cleaning.
- Balance sync and low-balance checks.
- SMS templates with placeholders.
- Settings and log storage.
- Admin panel: Dashboard, Settings, Templates, Integrations, Logs, Admin Alerts, Help.
- Default English and Arabic templates.
- `php artisan kwtsms:sync` command, scheduled daily.
- `KwtSms` facade.
- Events and a global kill switch.
- Test mode.
- Test suite, published config and migrations.
