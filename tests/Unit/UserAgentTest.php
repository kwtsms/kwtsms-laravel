<?php

/*
 * FILE:      tests/Unit/UserAgentTest.php
 * CREATED:   2026-11-05 14:55 +03
 * TASK:      #939
 * AUTHOR:    M Alhassoun
 * WHAT:      Checks the kwtsms-laravel/<version> token and that requests carry it.
 * WHY:       kwtSMS counts accounts per integration from the User-Agent.
 * USAGE:     vendor/bin/phpunit --filter UserAgentTest (offline: HTTP is faked, the client is not called).
 * DEPENDS:   PHPUnit, illuminate/http and illuminate/config (no database, no app boot), Composer\InstalledVersions.
 */

namespace KwtSMS\Laravel\Tests\Unit;

use Composer\InstalledVersions;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KwtSMS\KwtSMS;
use KwtSMS\Laravel\Services\BalanceService;
use KwtSMS\Laravel\Services\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function test_token_uses_installed_package_version(): void
    {
        $version = ltrim((string) InstalledVersions::getPrettyVersion(UserAgent::PACKAGE), 'vV');
        $expected = preg_match('/^[0-9]/', $version) === 1 ? $version : '0.0.0-dev';

        $this->assertSame('kwtsms-laravel/'.$expected, UserAgent::token());
        $this->assertMatchesRegularExpression('#^kwtsms-laravel/[0-9][0-9A-Za-z.+-]*$#', UserAgent::token());
    }

    public function test_balance_request_sends_user_agent(): void
    {
        $container = new Container;
        $container->instance('config', new Repository(['kwtsms' => ['username' => 'user', 'password' => 'pass']]));
        Container::setInstance($container);
        Http::swap($http = new Factory);
        $http->fake(['*' => $http->response(['result' => 'OK', 'available' => 10, 'purchased' => 10])]);

        $method = new \ReflectionMethod(BalanceService::class, 'fetchBalanceFromApi');
        $method->invoke((new \ReflectionClass(BalanceService::class))->newInstanceWithoutConstructor());

        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', UserAgent::token()));
    }

    public function test_library_client_gets_app_token(): void
    {
        $client = new KwtSMS('user', 'pass', 'KWT-SMS', true, '', UserAgent::token());
        if (! method_exists($client, 'getUserAgent')) {
            $this->markTestSkipped('Installed kwtsms-php has no app_name support yet');
        }

        $this->assertStringStartsWith(UserAgent::token().' kwtsms-php/', $client->getUserAgent());
    }
}
