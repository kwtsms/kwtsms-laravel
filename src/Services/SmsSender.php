<?php

namespace KwtSMS\Laravel\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use KwtSMS\KwtSMS;
use KwtSMS\Laravel\Events\SmsFailed;
use KwtSMS\Laravel\Events\SmsSent;
use KwtSMS\Laravel\Models\KwtSmsLog;
use KwtSMS\Laravel\Models\KwtSmsSetting;

/**
 * Core SMS sending service.
 *
 * One public method handles everything: single, multiple, and bulk (>200).
 *
 * Laravel concerns (this class):
 *   - Gateway on/off, test mode, credentials
 *   - Rate limiting (single recipient only)
 *   - Coverage filtering, SMS page estimation, balance check
 *   - DB logging with recipient_type and pages
 *   - Event dispatching (SmsSent, SmsFailed)
 *
 * Vendor library (kwtsms/kwtsms-php) handles:
 *   - Phone dedup, message cleaning, >200 batching, ERR013 retry
 */
class SmsSender
{
    private KwtSMS $client;

    private const MAX_SMS_PAGES = 7;

    /**
     * GSM-7 basic character set (single byte per char).
     */
    private const GSM7_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ ÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZ"
        .'ÄÖÑÜabcdefghijklmnopqrstuvwxyz§äöñüà';

    /**
     * GSM-7 extended characters (2 bytes per char: escape + char).
     */
    private const GSM7_EXTENDED = '{}[]\\~^|€';

    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly BalanceService $balanceService,
    ) {
        // 2026-11-05 14:55 +03: positional arguments so the 6th (app_name, User-Agent token) is ignored
        // by kwtsms-php versions without it; a named app_name would throw there, #939.
        $this->client = new KwtSMS(
            config('kwtsms.username', ''),       // username
            config('kwtsms.password', ''),       // password
            config('kwtsms.sender', 'KWT-SMS'),  // sender_id
            (bool) config('kwtsms.test_mode', false), // test_mode
            '',                                  // log_file
            UserAgent::token(),                  // app_name
        );
    }

    /**
     * Send an SMS message to one or more recipients.
     *
     * Handles single numbers, multiple numbers, and bulk (>200) transparently.
     * Respects global enabled/test mode toggles, rate limits, coverage, balance.
     *
     * @param  string|string[]  $recipients  Phone number(s). Normalized internally.
     * @param  string  $message  Message text. Cleaned internally by vendor library.
     * @param  string|null  $sender  Override sender ID. Defaults to config kwtsms.sender.
     * @param  array{event_type?: string, recipient_type?: string}  $options
     * @return array{success: bool, reason?: string, error_description?: string, message_id?: string|null, numbers_sent?: int, points_charged?: float, balance_after?: float|null, pages?: int, is_test?: bool, recipient_type?: string}
     */
    public function send(string|array $recipients, string $message, ?string $sender = null, array $options = []): array
    {
        $eventType = $options['event_type'] ?? 'custom';
        $recipientType = $options['recipient_type'] ?? 'customer';
        $effectiveSender = $sender ?? config('kwtsms.sender', 'KWT-SMS');
        $isTest = (bool) config('kwtsms.test_mode', false);

        // 1. Gateway check
        if (! config('kwtsms.enabled', true)) {
            if (config('kwtsms.debug_logging', false)) {
                Log::debug('KwtSMS: send skipped, gateway disabled');
            }

            return $this->fail('disabled', 'SMS gateway is disabled');
        }

        // 2. Rate limiting (single recipient only, before any I/O)
        $recipientArray = (array) $recipients;
        $rateLimited = $this->checkRateLimits($recipientArray);
        if ($rateLimited !== null) {
            return $rateLimited;
        }

        // 3. Credentials check
        if (empty(config('kwtsms.username')) || empty(config('kwtsms.password'))) {
            Log::warning('KwtSMS: send skipped, missing API credentials');

            return $this->fail('not_configured', 'API credentials not configured. Set KWTSMS_USERNAME and KWTSMS_PASSWORD in your .env file');
        }

        // 4. Normalize, validate, and deduplicate recipients
        $coverage = KwtSmsSetting::get('coverage', []);
        $coveragePrefixes = is_array($coverage) ? $coverage : [];

        if (! empty($coveragePrefixes)) {
            $normalized = $this->normalizer->normalizeMany($recipientArray, $coveragePrefixes);
            $recipientList = array_unique($normalized['valid']);
        } else {
            $normalized = $this->normalizer->normalizeMany($recipientArray);
            $recipientList = array_unique($normalized['valid']);
        }

        if (empty($recipientList)) {
            $reason = ! empty($coveragePrefixes) && ! empty($normalized['invalid'])
                ? 'no_coverage'
                : 'no_valid_recipients';
            $description = $reason === 'no_coverage'
                ? 'None of the provided numbers are in covered regions'
                : 'No valid phone numbers provided';

            return $this->fail($reason, $description);
        }

        // Re-index after array_unique
        $recipientList = array_values($recipientList);

        // 5. Calculate SMS pages and check max
        $pages = $this->calculateSmsPages($message);

        if ($pages > self::MAX_SMS_PAGES) {
            $isUcs2 = ! $this->isGsm7($message);
            $maxChars = $isUcs2
                ? 67 * self::MAX_SMS_PAGES
                : 153 * self::MAX_SMS_PAGES;

            return $this->fail(
                'message_too_long',
                "Message too long: {$pages} SMS pages (max ".self::MAX_SMS_PAGES."). Shorten to {$maxChars} characters"
            );
        }

        // 6. Balance check (cached, with stale sync)
        $balance = $this->balanceService->getCachedOrSync();
        $estimatedCost = $pages * count($recipientList);

        if ($balance !== null && $balance < $estimatedCost) {
            $count = count($recipientList);
            $this->logToDatabase(
                recipients: $recipientList,
                message: $message,
                sender: $effectiveSender,
                apiRequest: [],
                apiResponse: null,
                status: 'failed',
                errorCode: 'ERR010',
                errorMessage: 'Insufficient balance',
                eventType: $eventType,
                recipientType: $recipientType,
                pages: $pages,
            );

            return $this->fail(
                'insufficient_balance',
                "Insufficient balance: need ~{$estimatedCost} points ({$pages}p x {$count} recipients), have {$balance}. Recharge at kwtsms.com"
            );
        }

        // 7. Send via vendor library
        $response = $this->client->send($recipientList, $message, $effectiveSender);

        // Build sanitized api_request for logging (no credentials)
        $apiRequest = [
            'mobile' => implode(',', $recipientList),
            'message' => $message,
            'sender' => $effectiveSender,
            'test' => $isTest ? 1 : 0,
        ];

        $isOk = isset($response['result']) && $response['result'] === 'OK';
        $isPartial = isset($response['result']) && $response['result'] === 'PARTIAL';

        // 8. Update balance cache from API response
        if (isset($response['balance-after'])) {
            $this->balanceService->updateCache((float) $response['balance-after']);
        }

        // 9. Parse send timestamp
        $sentAt = null;
        if (isset($response['unix-timestamp'])) {
            $sentAt = Carbon::createFromTimestamp((int) $response['unix-timestamp'], 'Asia/Kuwait')->utc();
        }

        // Extract message ID(s)
        $messageId = $response['msg-id']
            ?? (isset($response['msg-ids']) ? implode(',', $response['msg-ids']) : null);
        $numbersSent = (int) ($response['numbers'] ?? count($recipientList));
        $pointsCharged = (float) ($response['points-charged'] ?? 0);
        $balanceAfter = isset($response['balance-after']) ? (float) $response['balance-after'] : null;

        // 10. Log to database
        $this->logToDatabase(
            recipients: $recipientList,
            message: $message,
            sender: $effectiveSender,
            apiRequest: $apiRequest,
            apiResponse: $response,
            status: ($isOk || $isPartial) ? 'sent' : 'failed',
            errorCode: ($isOk || $isPartial) ? null : ($response['code'] ?? null),
            errorMessage: ($isOk || $isPartial) ? null : ($response['description'] ?? null),
            eventType: $eventType,
            recipientType: $recipientType,
            pages: $pages,
            messageId: $messageId,
            numbersSent: $numbersSent,
            pointsCharged: $pointsCharged,
            balanceAfter: $balanceAfter,
            sentAt: $sentAt,
        );

        // 11. Dispatch events and return
        if ($isOk || $isPartial) {
            SmsSent::dispatch(
                implode(',', $recipientList),
                $message,
                $eventType,
                $messageId,
            );

            return [
                'success' => true,
                'message_id' => $messageId,
                'numbers_sent' => $numbersSent,
                'points_charged' => $pointsCharged,
                'balance_after' => $balanceAfter,
                'pages' => $pages,
                'is_test' => $isTest,
                'recipient_type' => $recipientType,
            ];
        }

        SmsFailed::dispatch(
            implode(',', $recipientList),
            $message,
            'api_error',
            $response['code'] ?? null,
        );

        Log::warning('KwtSMS: send failed', [
            'code' => $response['code'] ?? 'UNKNOWN',
            'description' => $response['description'] ?? '',
        ]);

        return $this->fail(
            $response['code'] ?? 'api_error',
            $response['description'] ?? 'Unknown API error',
        );
    }

    /**
     * Build a consistent failure response.
     *
     * @return array{success: false, reason: string, error_description: string}
     */
    private function fail(string $reason, string $description): array
    {
        return [
            'success' => false,
            'reason' => $reason,
            'error_description' => $description,
        ];
    }

    /**
     * Calculate how many SMS pages a message will use.
     *
     * GSM-7: 160 chars single, 153 chars/page multipart. Extended chars count as 2.
     * UCS-2: 70 chars single, 67 chars/page multipart.
     */
    private function calculateSmsPages(string $message): int
    {
        if ($this->isGsm7($message)) {
            $charCount = $this->gsm7Length($message);

            return $charCount <= 160 ? 1 : (int) ceil($charCount / 153);
        }

        $charCount = mb_strlen($message);

        return $charCount <= 70 ? 1 : (int) ceil($charCount / 67);
    }

    /**
     * Check if a message uses only GSM-7 characters (basic + extended).
     */
    private function isGsm7(string $message): bool
    {
        for ($i = 0; $i < mb_strlen($message); $i++) {
            $char = mb_substr($message, $i, 1);

            if (str_contains(self::GSM7_BASIC, $char) || str_contains(self::GSM7_EXTENDED, $char)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Calculate effective GSM-7 character length (extended chars count as 2).
     */
    private function gsm7Length(string $message): int
    {
        $length = 0;

        for ($i = 0; $i < mb_strlen($message); $i++) {
            $char = mb_substr($message, $i, 1);
            $length += str_contains(self::GSM7_EXTENDED, $char) ? 2 : 1;
        }

        return $length;
    }

    /**
     * Check per-IP and per-phone rate limits before sending.
     *
     * Only applies to single-recipient sends (OTP, password reset).
     * Bulk sends skip rate limiting.
     *
     * @param  string[]  $recipients
     * @return array{success: false, reason: string, error_description: string}|null
     */
    private function checkRateLimits(array $recipients): ?array
    {
        if (count($recipients) !== 1) {
            return null;
        }

        // Per-IP limit (HTTP context only)
        if (! app()->runningInConsole()) {
            $ip = request()->ip();
            $ipKey = 'kwtsms:ip:'.$ip;
            $ipLimit = (int) KwtSmsSetting::get('rate_limit_per_ip', config('kwtsms.rate_limit.per_ip_per_hour', 10));

            if (RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
                Log::warning('KwtSMS: send blocked, IP rate limit exceeded', ['ip' => $ip]);

                return $this->fail('rate_limited', 'Rate limited: too many SMS from this IP');
            }

            RateLimiter::hit($ipKey, 3600);
        }

        // Per-phone limit
        $phone = $this->normalizer->normalize((string) $recipients[0]);
        $phoneKey = 'kwtsms:phone:'.$phone;
        $phoneLimit = (int) KwtSmsSetting::get('rate_limit_per_phone', config('kwtsms.rate_limit.per_phone_per_hour', 5));

        if (RateLimiter::tooManyAttempts($phoneKey, $phoneLimit)) {
            Log::warning('KwtSMS: send blocked, per-phone rate limit exceeded', [
                'phone_suffix' => substr($phone, -4),
            ]);

            return $this->fail('rate_limited', 'Rate limited: too many SMS to this number');
        }

        RateLimiter::hit($phoneKey, 3600);

        return null;
    }

    /**
     * Write an SMS attempt record to the kwtsms_logs table.
     *
     * @param  string[]  $recipients
     * @param  array<string, mixed>  $apiRequest
     * @param  array<string, mixed>|null  $apiResponse
     */
    private function logToDatabase(
        array $recipients,
        string $message,
        string $sender,
        array $apiRequest,
        ?array $apiResponse,
        string $status,
        ?string $errorCode,
        ?string $errorMessage,
        ?string $eventType,
        string $recipientType = 'customer',
        int $pages = 1,
        ?string $messageId = null,
        int $numbersSent = 0,
        float $pointsCharged = 0.0,
        ?float $balanceAfter = null,
        ?Carbon $sentAt = null,
    ): void {
        try {
            KwtSmsLog::create([
                'message_id' => $messageId,
                'recipient' => implode(',', $recipients),
                'sender_id' => $sender,
                'message' => $message,
                'status' => $status,
                'event_type' => $eventType,
                'recipient_type' => $recipientType,
                'is_test' => (bool) config('kwtsms.test_mode', false),
                'pages' => $pages,
                'numbers_sent' => $numbersSent,
                'points_charged' => $pointsCharged,
                'balance_after' => $balanceAfter,
                'api_request' => empty($apiRequest) ? null : $apiRequest,
                'api_response' => $apiResponse,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'sent_at' => $sentAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('KwtSMS: failed to write SMS log', ['error' => $e->getMessage()]);
        }
    }
}
