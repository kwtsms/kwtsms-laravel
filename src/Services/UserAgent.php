<?php

/*
 * FILE:      src/Services/UserAgent.php
 * CREATED:   2026-11-05 14:55 +03
 * TASK:      #939
 * AUTHOR:    M Alhassoun
 * WHAT:      Builds the product token "kwtsms-laravel/<installed package version>".
 * WHY:       Every request to kwtSMS names the integration and its version (no personal data) so
 *            kwtSMS can count accounts per integration.
 * USAGE:     UserAgent::token(); passed as the kwtsms-php app_name and as the User-Agent of direct HTTP calls.
 * DEPENDS:   Composer\InstalledVersions (Composer 2).
 */

namespace KwtSMS\Laravel\Services;

use Composer\InstalledVersions;

class UserAgent
{
    public const PACKAGE = 'kwtsms/laravel-kwtsms';

    /**
     * "kwtsms-laravel/<version>", the version read from Composer's installed metadata (tag, "v" dropped).
     * A dev checkout without a tag reports "0.0.0-dev".
     */
    public static function token(): string
    {
        $version = '';
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
            $version = ltrim((string) InstalledVersions::getPrettyVersion(self::PACKAGE), 'vV');
        }
        if (preg_match('/^[0-9][0-9A-Za-z.+-]*$/', $version) !== 1) {
            $version = '0.0.0-dev';
        }

        return 'kwtsms-laravel/'.$version;
    }
}
