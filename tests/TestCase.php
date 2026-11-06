<?php

namespace KwtSMS\Laravel\Tests;

use KwtSMS\Laravel\KwtSmsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            KwtSmsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('kwtsms.username', env('KWTSMS_USERNAME', ''));
        $app['config']->set('kwtsms.password', env('KWTSMS_PASSWORD', ''));
        $app['config']->set('kwtsms.sender', env('KWTSMS_SENDER', 'KWT-SMS'));
        $app['config']->set('kwtsms.test_mode', true);
        $app['config']->set('kwtsms.enabled', true);
        $app['config']->set('database.default', 'testing');
    }

    /**
     * Test number for live test=1 sends, from KWTSMS_TEST_NUMBER (965XXXXXXXX).
     * Skips the test when unset or not a Kuwait mobile; the value is never printed.
     *
     * 2026-11-05 11:25 +03: live sends use this instead of a hard-coded sample mobile
     * (sample numbers belong to real people), int-testnumber.
     */
    protected function kwtsmsTestNumber(): string
    {
        $number = (string) getenv('KWTSMS_TEST_NUMBER');
        if (preg_match('/^965[4569]\d{7}$/', $number) !== 1) {
            $this->markTestSkipped('KWTSMS_TEST_NUMBER unset or not 965XXXXXXXX');
        }

        return $number;
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
