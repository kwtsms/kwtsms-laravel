<?php

namespace KwtSMS\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use KwtSMS\Laravel\Services\SmsSender;

/**
 * KwtSms Facade
 *
 * Provides static access to the SmsSender service.
 *
 * Usage:
 *   KwtSms::send('965XXXXXXXX', 'Your message here');
 *   KwtSms::send(['965XXXXXXXX', '965XXXXXXXX'], 'Bulk message');
 *   (2026-11-05 11:24 +03: examples use the placeholder 965XXXXXXXX instead of sample
 *   mobile numbers, sample numbers belong to real people, int-testnumber)
 *
 * @see SmsSender
 *
 * @method static array send(string|array $recipients, string $message, ?string $sender = null, array $options = [])
 */
class KwtSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsSender::class;
    }
}
