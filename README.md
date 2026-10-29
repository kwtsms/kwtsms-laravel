# kwtSMS for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kwtsms/laravel-kwtsms.svg?style=flat-square)](https://packagist.org/packages/kwtsms/laravel-kwtsms)
[![Total Downloads](https://img.shields.io/packagist/dt/kwtsms/laravel-kwtsms.svg?style=flat-square)](https://packagist.org/packages/kwtsms/laravel-kwtsms)
[![PHP Version](https://img.shields.io/packagist/php-v/kwtsms/laravel-kwtsms.svg?style=flat-square)](https://packagist.org/packages/kwtsms/laravel-kwtsms)
[![Code Style](https://img.shields.io/github/actions/workflow/status/kwtsms/kwtsms-laravel/pint.yml?branch=main&label=code+style&style=flat-square)](https://github.com/kwtsms/kwtsms-laravel/actions/workflows/pint.yml)
[![PHP](https://img.shields.io/github/actions/workflow/status/kwtsms/kwtsms-laravel/php.yml?branch=main&label=php+8.1–8.3&style=flat-square)](https://github.com/kwtsms/kwtsms-laravel/actions/workflows/php.yml)
[![License](https://img.shields.io/github/license/kwtsms/kwtsms-laravel?style=flat-square)](LICENSE)

A Laravel notification channel package for the [kwtSMS](https://www.kwtsms.com) SMS gateway (Kuwait). Send SMS messages through kwtSMS in any Laravel 10/11/12 application.

## About kwtSMS

[kwtSMS](https://www.kwtsms.com) is a Kuwait-based SMS gateway trusted by businesses to deliver messages across Kuwait (Zain, Ooredoo, STC, Virgin) and internationally. It offers private Sender IDs, free API testing, non-expiring credits, and competitive flat-rate pricing. Open a free account in under one minute at [kwtsms.com](https://www.kwtsms.com/signup/), no paperwork or payment required.

---

## Features

- Laravel Notification Channel integration (standard `$user->notify(...)` syntax)
- Admin panel at `/kwtsms`: Dashboard, Settings, Templates, Integrations, Logs, Admin Alerts, Help
- OTP and password reset SMS support with built-in rate limiting
- Multilingual SMS templates: English and Arabic (RTL ready) with `{{variable}}` placeholders
- Phone number normalization (strips `+`, `00`, spaces, dashes, Arabic/Hindi digits)
- Message cleaning (strips emojis, hidden characters, HTML before send)
- Bulk send with batching (max 200 numbers per request, 0.2s delay between batches)
- Pre-send balance check (cached, skips API call if zero balance)
- Coverage-aware sending (skips numbers from inactive country prefixes)
- Full SMS log in local database with export to CSV and clear/purge option
- Daily scheduled sync command for balance, sender IDs, and coverage
- Test mode support (`KWTSMS_TEST_MODE=true`: queued without delivery, credits recoverable)
- Global on/off kill switch (`KWTSMS_ENABLED=false`)
- Admin alert notifications (low balance, send failure, daily summary, API errors, OTP flood)

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require kwtsms/laravel-kwtsms
```

Publish configuration and run migrations:

```bash
php artisan vendor:publish --tag=kwtsms-config
php artisan vendor:publish --tag=kwtsms-migrations
php artisan migrate
```

Optionally seed the default SMS templates (English + Arabic for all event types):

```bash
php artisan db:seed --class=KwtSMS\\Laravel\\Database\\Seeders\\KwtSmsDefaultTemplatesSeeder
```

## Configuration

Add your kwtSMS API credentials to `.env`:

```env
KWTSMS_USERNAME=your_api_username
KWTSMS_PASSWORD=your_api_password
KWTSMS_SENDER=YOUR-SENDERID
KWTSMS_TEST_MODE=false
KWTSMS_ENABLED=true
```

> **Note:** Use your API username and password from your kwtSMS account API settings page, not your mobile number.

> **Sender ID:** `KWT-SMS` is a shared test sender only. Register a private sender ID on your kwtSMS account before going live. Transactional sender IDs bypass DND lists and are required for OTP delivery.

## Quick Start

### Notification Channel

```php
use KwtSMS\Laravel\Channels\KwtSmsChannel;
use KwtSMS\Laravel\Notifications\KwtSmsMessage;

class OrderShipped extends Notification
{
    public function via($notifiable): array
    {
        return [KwtSmsChannel::class];
    }

    public function toKwtSms($notifiable): KwtSmsMessage
    {
        return KwtSmsMessage::create()
            ->content("Your order has been shipped. Track: {$this->trackingCode}");
    }
}
```

Your notifiable model must implement `routeNotificationForKwtSms()`:

```php
public function routeNotificationForKwtSms(): string
{
    return $this->phone; // e.g. "96598765432" (international format, digits only)
}
```

Then send as normal:

```php
$user->notify(new OrderShipped($order));
```

### Direct Send via SmsSender

```php
use KwtSMS\Laravel\Services\SmsSender;

$sender = app(SmsSender::class);

// Single recipient
$result = $sender->send('96598765432', 'Hello from kwtSMS!');

// Multiple recipients (batched automatically)
$result = $sender->send(['96598765432', '96512345678'], 'Bulk message');

// With event type (used for template lookup and logging)
$result = $sender->send('96598765432', 'Your OTP is: 123456', null, [
    'event_type' => 'otp',
]);
```

Response format:

```php
// Success
['success' => true, 'message_id' => 'abc123...', 'numbers_sent' => 1, 'points_charged' => 1, 'balance_after' => 150]

// Failure
['success' => false, 'reason' => 'ERR003', 'error_description' => 'Authentication error...']

// Blocked by guards
['success' => false, 'reason' => 'disabled']             // KWTSMS_ENABLED=false
['success' => false, 'reason' => 'rate_limited']         // per-IP or per-phone limit exceeded
['success' => false, 'reason' => 'no_balance']           // cached balance is zero
['success' => false, 'reason' => 'no_valid_recipients']  // empty list or all out of coverage
```

### Sending for Events (Template-Based)

```php
$sender->sendForEvent('order_placed', '96598765432', [
    'customer_name' => 'Ahmed',
    'order_id' => '#1234',
    'total' => '25.500',
]);
```

This looks up the active template with `event_type = 'order_placed'` and locale matching the user, substitutes `{{customer_name}}`, `{{order_id}}`, `{{total}}`, and sends.

### Facade

```php
use KwtSMS\Laravel\Facades\KwtSms;

KwtSms::send('96598765432', 'Hello!');
KwtSms::balance();
KwtSms::senderids();
```

## SMS Templates

Templates are managed via the admin panel at `/kwtsms/templates`. Each template has:
- A name (e.g. `otp_en`)
- An event type (e.g. `otp`, `order_placed`, `password_reset`)
- A locale (`en` or `ar`)
- A message body with `{{variable_name}}` placeholders

Example template body:

```
Your OTP for {{app_name}} is: {{otp_code}}. Valid for {{expiry_minutes}} minutes. Do not share this code.
```

Rendered with:

```php
$template->render([
    'app_name' => 'MyApp',
    'otp_code' => '123456',
    'expiry_minutes' => '5',
]);
// Output: Your OTP for MyApp is: 123456. Valid for 5 minutes. Do not share this code.
```

Default templates are seeded for: `otp`, `password_reset`, `order_placed`, `order_confirmed`, `order_shipped`, `order_delivered`, `order_cancelled`, `order_status`, `cod_otp`, `low_balance_alert`.

## Admin Panel

After installation, visit `/kwtsms` to access the admin panel. It provides:

| Tab | Description |
|-----|-------------|
| Dashboard | Balance, send statistics, recent logs |
| Settings | API credentials, test connection, low balance threshold, admin phone |
| Templates | Create, edit, activate/deactivate SMS templates |
| Integrations | Toggle which events trigger SMS sends |
| Logs | View and export the full SMS send history |
| Admin Alerts | Configure alert notifications to the admin phone |
| Help | Quick start guide and code examples |

> **Auth:** The admin panel uses the `admin_middleware` setting from `config/kwtsms.php`. Default is `['web', 'auth']`.

## Artisan Commands

```bash
# Sync balance, sender IDs, and coverage from the kwtSMS API
php artisan kwtsms:sync

# Force sync even if recently synced
php artisan kwtsms:sync --force
```

The sync command runs automatically every day at 03:00 (Asia/Kuwait) via the scheduler.

## Phone Number Format

All phone numbers must be in international format, digits only, no prefix:

```
96598765432    correct
+96598765432   wrong (strip +)
0096598765432  wrong (strip 00)
965 9876 5432  wrong (strip spaces)
```

The package normalizes numbers automatically before every send. Numbers containing Arabic/Hindi digits are also converted.

## Test Mode

When `KWTSMS_TEST_MODE=true`:
- Messages are queued on kwtSMS servers but not delivered to handsets
- Credits are not consumed (tentatively held until you delete from the queue)
- Test messages appear in your kwtSMS account Sending Queue
- Delete them from the queue at kwtsms.com to release any held credits

Always use test mode during development. Set `KWTSMS_TEST_MODE=false` only in production.

## Help and Support

- **[kwtSMS FAQ](https://www.kwtsms.com/faq/)**: Answers to common questions about credits, sender IDs, OTP, and delivery
- **[kwtSMS Support](https://www.kwtsms.com/support.html)**: Open a support ticket or browse help articles
- **[Contact kwtSMS](https://www.kwtsms.com/#contact)**: Reach the kwtSMS team directly for Sender ID registration and account issues
- **[API Documentation (PDF)](https://www.kwtsms.com/doc/KwtSMS.com_API_Documentation_v41.pdf)**: kwtSMS REST API v4.1 full reference
- **[Best Practices](https://www.kwtsms.com/articles/sms-api-implementation-best-practices.html)**: SMS API implementation best practices
- **[Integration Test Checklist](https://www.kwtsms.com/articles/sms-api-integration-test-checklist.html)**: Pre-launch testing checklist
- **[Sender ID Help](https://www.kwtsms.com/sender-id-help.html)**: How to register, whitelist, and troubleshoot sender IDs
- **[kwtSMS Dashboard](https://www.kwtsms.com/login/)**: Recharge credits, buy Sender IDs, view message logs, manage coverage
- **[Other Integrations](https://www.kwtsms.com/integrations.html)**: Plugins and integrations for other platforms and languages

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
