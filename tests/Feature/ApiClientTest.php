<?php

namespace KwtSMS\Laravel\Tests\Feature;

use KwtSMS\KwtSMS;
use KwtSMS\Laravel\Services\BalanceService;
use KwtSMS\Laravel\Tests\TestCase;

class ApiClientTest extends TestCase
{
    private function kwtSmsCredentialsConfigured(): bool
    {
        return ! empty(config('kwtsms.username')) && ! empty(config('kwtsms.password'));
    }

    public function test_balance_returns_ok(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $service = app(BalanceService::class);
        $result = $service->syncFromApi();

        $this->assertArrayHasKey('available', $result);
        $this->assertIsFloat($result['available']);
        $this->assertGreaterThan(0, $result['available']);
    }

    public function test_senderid_returns_list(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $client = new KwtSMS(
            username: config('kwtsms.username'),
            password: config('kwtsms.password'),
            sender_id: 'KWT-SMS',
            test_mode: true,
            log_file: '',
        );

        $result = $client->senderids();
        $this->assertSame('OK', $result['result']);
        $this->assertIsArray($result['senderids']);
        $this->assertNotEmpty($result['senderids']);
    }

    public function test_coverage_returns_prefixes(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $client = new KwtSMS(
            username: config('kwtsms.username'),
            password: config('kwtsms.password'),
            sender_id: 'KWT-SMS',
            test_mode: true,
            log_file: '',
        );

        $result = $client->coverage();
        $this->assertSame('OK', $result['result']);
        $this->assertIsArray($result['prefixes']);
        $this->assertContains('965', $result['prefixes']);
    }

    public function test_send_with_test_mode(): void
    {
        if (! $this->kwtSmsCredentialsConfigured()) {
            $this->markTestSkipped('kwtSMS credentials not configured');
        }

        $client = new KwtSMS(
            username: config('kwtsms.username'),
            password: config('kwtsms.password'),
            sender_id: config('kwtsms.sender', 'KWT-SMS'),
            test_mode: true,
            log_file: '',
        );

        // 2026-11-05 11:25 +03: recipient from KWTSMS_TEST_NUMBER, not a sample mobile, int-testnumber
        $result = $client->send([$this->kwtsmsTestNumber()], 'Test message from ApiClientTest - test mode', config('kwtsms.sender', 'KWT-SMS'));
        $this->assertSame('OK', $result['result']);
        $this->assertArrayHasKey('msg-id', $result);
        $this->assertNotEmpty($result['msg-id']);
    }
}
