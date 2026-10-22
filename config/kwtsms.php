<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable/Disable kwtSMS globally
    |--------------------------------------------------------------------------
    | When disabled, no SMS will be sent. Use this to turn off SMS without
    | removing the package or changing other settings.
    */
    'enabled' => env('KWTSMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    | When true, messages are queued by the API but not delivered to handsets.
    | Credits are held until you delete the test messages from the Sending
    | Queue in your kwtsms.com account. Always true during development.
    | 2026-10-22 15:36 +03: corrected, test sends hold credits (was "not consumed").
    */
    'test_mode' => env('KWTSMS_TEST_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    | Your kwtSMS API username and password. These are NOT your account mobile
    | number - they are the API credentials from your kwtSMS account settings.
    */
    'username' => env('KWTSMS_USERNAME', ''),
    'password' => env('KWTSMS_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Sender ID
    |--------------------------------------------------------------------------
    | The sender name shown to SMS recipients. Must be registered on your
    | kwtSMS account. 'KWT-SMS' is for testing only - never use in production.
    */
    'sender' => env('KWTSMS_SENDER', 'KWT-SMS'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    | The kwtSMS REST/JSON API base URL. Must end with a trailing slash.
    */
    'api_base_url' => env('KWTSMS_API_URL', 'https://www.kwtsms.com/API/'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => env('KWTSMS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    */
    'admin_route_prefix' => env('KWTSMS_ADMIN_PREFIX', 'kwtsms'),

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to all admin panel routes. If your application uses
    | a custom auth guard (e.g. 'auth:admin'), update 'auth' to match.
    | Default: ['web', 'auth'] uses the default web guard.
    */
    'admin_middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'debug_logging' => env('KWTSMS_DEBUG', false),
    'log_retention_days' => env('KWTSMS_LOG_RETENTION', 90),

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'expiry_minutes' => env('KWTSMS_OTP_EXPIRY', 5),
        'resend_cooldown' => env('KWTSMS_OTP_COOLDOWN', 3),
        'max_attempts_hour' => env('KWTSMS_OTP_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_phone_per_hour' => env('KWTSMS_RATE_PHONE', 5),
        'per_ip_per_hour' => env('KWTSMS_RATE_IP', 10),
    ],
];
