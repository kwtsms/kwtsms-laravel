<?php

namespace KwtSMS\Laravel\Tests\Feature;

use KwtSMS\Laravel\Models\KwtSmsSetting;
use KwtSMS\Laravel\Services\SmsSender;
use KwtSMS\Laravel\Tests\TestCase;

/**
 * 2026-11-05 11:25 +03: live test=1 sends go to KWTSMS_TEST_NUMBER via kwtsmsTestNumber();
 * guard tests that never reach the API use 99912345678 (country code 999 is unassigned)
 * instead of a sample Kuwait mobile that belongs to a real person, int-testnumber.
 */
class SmsSenderTest extends TestCase
{
    private function kwtSmsCredentialsConfigured(): bool
    {
        return ! empty(config('kwtsms.username')) && ! empty(config('kwtsms.password'));
    }

    public function test_send_single_number(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $sender = app(SmsSender::class);
        $result = $sender->send($this->kwtsmsTestNumber(), 'SmsSenderTest: single number. Test mode.', null, ['event_type' => 'test']);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message_id', $result);
    }

    public function test_send_normalizes_phone_format(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $sender = app(SmsSender::class);
        // Pass with + prefix - should normalize and send
        $result = $sender->send('+'.$this->kwtsmsTestNumber(), 'SmsSenderTest: normalized phone. Test mode.', null, ['event_type' => 'test']);

        $this->assertTrue($result['success']);
    }

    public function test_send_blocked_when_disabled(): void
    {
        config(['kwtsms.enabled' => false]);

        $sender = app(SmsSender::class);
        $result = $sender->send('99912345678', 'This should not send', null, ['event_type' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertSame('disabled', $result['reason']);

        config(['kwtsms.enabled' => true]);
    }

    public function test_send_blocked_when_zero_balance(): void
    {
        // 2026-11-07 02:19 +03: placeholder credentials so the send reaches the balance check; a cached balance of 0 stops it
        // before any API call. Without them the test failed with not_configured. Task q-track-tests.
        config(['kwtsms.username' => 'laravel_username', 'kwtsms.password' => 'laravel_password']);
        // Set balance to 0 in the settings cache
        KwtSmsSetting::set('balance_available', 0);
        // 2026-11-07 02:20 +03: mark the balance as just synced, or getCachedOrSync() calls the API with the placeholder login
        // (a failed login on every CI run). Task q-track-tests.
        KwtSmsSetting::set('balance_synced_at', now()->toIso8601String());

        $sender = app(SmsSender::class);
        $result = $sender->send('99912345678', 'This should not send', null, ['event_type' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_balance', $result['reason']);

        // Clean up
        KwtSmsSetting::set('balance_available', null);
        KwtSmsSetting::set('balance_synced_at', null);
    }

    public function test_send_skips_uncovered_country(): void
    {
        // 2026-11-07 02:19 +03: placeholder credentials so the send reaches the coverage filter, which stops it before any API
        // call. The reason for a number outside coverage is no_coverage (SmsSender), not no_valid_recipients. Task q-track-tests.
        config(['kwtsms.username' => 'laravel_username', 'kwtsms.password' => 'laravel_password']);
        // Set coverage to only Kuwait (965), then try to send to UAE (971)
        KwtSmsSetting::set('coverage', ['965']);

        $sender = app(SmsSender::class);
        $result = $sender->send('97112345678', 'This should not send - UAE not covered', null, ['event_type' => 'test']);

        $this->assertFalse($result['success']);
        $this->assertSame('no_coverage', $result['reason']);

        // Clean up
        KwtSmsSetting::set('coverage', []);
    }

    public function test_arabic_message_sends_correctly(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $sender = app(SmsSender::class);
        $result = $sender->send(
            $this->kwtsmsTestNumber(),
            'رسالة اختبار: هذه رسالة عربية من حزمة Laravel.',
            null,
            ['event_type' => 'test']
        );

        $this->assertTrue($result['success']);
    }
}
