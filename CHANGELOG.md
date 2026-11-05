# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- README and code examples use the placeholder 965XXXXXXXX instead of a sample mobile number.

### Docs

- Test mode: test messages are queued but not delivered. Their credits are held until you delete them from the Sending Queue in your kwtsms.com account (they were wrongly described as not consumed).

## [1.0.12] - 2026-03-16

### Changed

- **SmsSender:** Rewritten with unified send() method. One function handles single, multiple, and bulk sends.
- **SmsSender:** Added SMS page calculator with GSM-7/UCS-2 encoding detection and cost estimation.
- **SmsSender:** Balance check now syncs from API if cached value is stale (>24h) or unknown.
- **SmsSender:** All failure responses now include both machine-readable `reason` and human-readable `error_description`.
- **SmsSender:** Dispatches SmsSent and SmsFailed events after every send.
- **BalanceService:** Added getCachedOrSync() with 24-hour stale threshold.
- **KwtSmsEventSubscriber:** Admin alert sends now tagged with recipient_type 'admin'.

### Added

- `recipient_type` column in kwtsms_logs (customer or admin)
- `pages` column in kwtsms_logs (SMS page count)
- `is_test`, `pages`, `recipient_type` fields in send() success response

## [1.0.11] - 2026-03-15

### Added

- GitHub Actions workflow to auto-create GitHub Releases with changelog notes on tag push

## [1.0.10] - 2026-03-15

### Added

- Unit tests for default event type and fluent method chaining on KwtSmsMessage

## [1.0.9] - 2026-03-13

### Changed

- **PhoneNormalizer:** Removed duplicated `PHONE_RULES`, `COUNTRY_NAMES`, `findCountryCode`, and `validatePhoneFormat` implementations. All logic now delegates to `KwtSMS\PhoneUtils` v1.3.0, which ships the same rules and methods. The public interface (`normalize`, `findCountryCode`, `validatePhoneFormat`, `verify`, `normalizeMany`) is unchanged.
- Requires `kwtsms/kwtsms-php` ^1.3.0 (updated via `composer update kwtsms/kwtsms-php`).

## [1.0.8] - 2026-03-13

### Fixed

- **PhoneNormalizer:** `normalize()` now strips the trunk prefix `0` from the local portion when a country code is recognized (e.g. `9660559...` becomes `966559...`). Covers Saudi Arabia and any other country where users supply international format with a local trunk zero.

## [1.0.7] - 2026-03-13

### Added

- **PhoneNormalizer:** Country-specific phone format validation ported from the kwtSMS Shopify integration. Added `PHONE_RULES` table (81 countries), `findCountryCode()`, and `validatePhoneFormat()`. `verify()` now rejects landlines and wrong-length numbers for all known country codes. Unknown country codes continue to pass with generic E.164 validation (7-15 digits).

## [1.0.6] - 2026-03-13

### Fixed

- **LOW:** Template create/edit form help text used single-brace `{variable_name}` syntax (which the template engine does not support). Corrected to `{{variable_name}}` (double braces) in both the placeholder text and help line.
- **LOW:** `help.blade.php` Template Variables card showed single-brace `{variable_name}` syntax. Corrected to `{{variable_name}}` to match the actual template engine.
- **LOW:** Settings page Rate Limiting description incorrectly stated per-IP limit applies to all web requests. Corrected to clarify both per-IP and per-phone limits apply to single-recipient sends only.

## [1.0.5] - 2026-03-13

### Fixed

- **CRIT:** Admin alerts never fired because `KwtSmsEventSubscriber` read the wrong settings key (`alert_phone` instead of `admin_phone`).
- **CRIT:** Settings page partially revealed the API username (first 3 characters visible). Now shows only a Set/Not Set badge, matching password behavior.
- **CRIT:** Admin Alerts page displayed the admin phone number in plaintext. Now masked to last 4 digits.
- **HIGH:** `KwtSmsSetting::get()` silently returned raw ciphertext when called on an encrypted key. Now logs a warning and returns the default instead.
- **HIGH:** Per-IP rate limit incorrectly applied to bulk sends (order notifications) triggered from HTTP context. Limit now applies to single-recipient sends only, matching per-phone behavior.
- **HIGH:** `LogsController::clear()` accepted any DELETE request without additional confirmation. Now requires `confirm=yes` in the request body.
- **HIGH:** Admin phone validation accepted arbitrary strings. Now enforces `digits_between:7,15` for international format.
- **HIGH:** `IntegrationsUpdateRequest` accepted arbitrary values in the `integrations` array. Added `integrations.*` rule enforcing boolean values.
- **MED:** `KwtSmsSetting::set()` silently overwrote encrypted keys with plaintext. Now logs a warning before overwriting.
- **MED:** `kwtsms_logs.recipient` column was `varchar(30)`, truncating comma-separated phone lists for bulk sends. New migration widens to `text`.
- **MED:** `kwtsms_templates.name` had a single-column unique constraint, preventing the same template name in multiple locales. New migration replaces it with a composite unique on `(name, locale)`.
- **MED:** `BalanceService` used raw cURL. Replaced with Laravel Http facade for consistency and testability.
- **MED:** Template body had no length limit. Added `max:1600` (10 SMS pages).
- **LOW:** `SmsFailed` event exposed the full recipient phone number as a public property. Added `recipientMasked()` helper method for safe logging.
- **LOW:** `KwtSmsSetting::set()` stored plain strings without JSON-encoding, causing inconsistent round-trips for numeric strings via `get()`. All values are now JSON-encoded on write.
- **LOW:** `TemplateRequest` had no uniqueness validation. Added `Rule::unique` on `(name, locale)` with `ignore` support for updates.
- **LOW:** `integrations.blade.php` referenced the non-existent `SmsSender::sendForEvent()` method. Updated to show the correct `SmsSender::send()` API.
- **LOW:** `help.blade.php` referenced the non-existent `$sender->sendForEvent()` method and showed incorrect single-brace template syntax. Updated to use `send()` and double-brace `@{{variable}}` syntax.

## [1.0.3] - 2026-03-13

### Fixed

- Rate limiting config keys (`per_phone_per_hour`, `per_ip_per_hour`) were defined in config but never enforced. Now applied in `SmsSender::send()` using Laravel's built-in `RateLimiter`. Per-phone limit applies to single-recipient sends (OTP/password reset). Per-IP limit applies in HTTP context only (skipped in CLI/queues).

## [1.0.2] - 2026-03-13

### Changed

- Restructured README: moved About kwtSMS section to top, License to bottom
- Replaced Help and Support table with linked list matching style of other kwtSMS packages
- Removed Security section, WhatsApp number, and email address from README (refer to SECURITY.md)
- Removed kwtSMS SDKs and Integrations section from README
- Fixed em dash in About kwtSMS paragraph

## [1.0.1] - 2026-03-13

### Added

- GitHub Actions CI: Laravel Pint code style check (`pint.yml`) on push/PR to main
- GitHub Actions CI: PHP syntax check across 8.1, 8.2, 8.3 (`php.yml`) on push/PR to main

### Changed

- Added `laravel/pint` to `require-dev` in `composer.json`

---

## [1.0.0] - 2026-03-13

### Added

- **kwtSMS REST/JSON API client** (`KwtSMS\KwtSMS`): send, balance, senderids, validate, coverage, status, dlr endpoints
- **Laravel Notification Channel** (`KwtSmsChannel` + `KwtSmsMessage`): plugs into standard `$notifiable->notify()` flow
- **SmsSender service**: core send pipeline with pre-send guards (enabled, configured, balance, coverage)
- **Bulk send with batching**: max 200 numbers per request, 0.2s delay between batches to stay within API rate limits
- **PhoneNormalizer service**: strips `+`, `00`, spaces, dashes, converts Arabic/Hindi digits to Latin
- **MessageUtils (MessageCleaner)**: strips emojis, zero-width characters, HTML tags, converts Arabic numerals
- **BalanceService**: syncs and caches account balance, powers low-balance threshold checks
- **KwtSmsTemplate model**: SMS templates with `{{variable_name}}` placeholder substitution
- **KwtSmsSetting model**: key/value settings store backed by the `kwtsms_settings` table
- **KwtSmsLog model**: per-send log with full API request/response JSON, event type, status, points charged
- **Admin panel** (7 tabs): Dashboard, Settings, Templates, Integrations, Logs, Admin Alerts, Help
  - Dashboard: live balance, send count, failed count, recent logs
  - Settings: credentials, test connection button, low balance threshold, admin phone
  - Templates: create/edit/delete/activate templates with Arabic character counter
  - Integrations: toggle which events trigger SMS sends
  - Logs: view, export CSV, clear all
  - Admin Alerts: configure alert types (low balance, send failure, daily summary, API error, OTP flood)
  - Help: quick start guide and code examples
- **Default SMS templates** (English + Arabic) for: `otp`, `password_reset`, `order_placed`, `order_confirmed`, `order_shipped`, `order_delivered`, `order_cancelled`, `order_status`, `cod_otp`, `low_balance_alert`
- **KwtSmsSyncCommand** (`php artisan kwtsms:sync`): syncs balance, sender IDs, and coverage from API; scheduled daily at 03:00
- **KwtSms Facade** for static access: `KwtSms::send()`, `KwtSms::balance()`, `KwtSms::senderids()`
- **Event system**: `BalanceLow` event, `KwtSmsNotifiable` contract, order/auth event integration hooks
- **Global kill switch**: `KWTSMS_ENABLED=false` blocks all sends without touching other config
- **Test mode**: `KWTSMS_TEST_MODE=true` queues messages without delivery, no credits consumed
- **PHPUnit feature test suite**: 27 tests, 44 assertions, all hitting real API with test mode
- **Published config**: `config/kwtsms.php` with all settings documented
- **Published migrations**: `kwtsms_logs`, `kwtsms_settings`, `kwtsms_templates` tables
