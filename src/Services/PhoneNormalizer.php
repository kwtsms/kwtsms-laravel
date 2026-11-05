<?php

namespace KwtSMS\Laravel\Services;

use KwtSMS\PhoneUtils;

/**
 * Phone number normalization, validation, and coverage verification service.
 *
 * Thin wrapper around KwtSMS\PhoneUtils (v1.3.0+) which provides PHONE_RULES,
 * find_country_code(), validate_phone_format(), and validate_phone_input()
 * (including trunk-0 stripping). This class adds coverage-prefix filtering
 * for the pre-send check in SmsSender and exposes camelCase aliases for the
 * static PhoneUtils methods.
 */
class PhoneNormalizer
{
    /**
     * Normalize a phone number to kwtSMS-accepted format (digits only, international format).
     *
     * Delegates to PhoneUtils::validate_phone_input(), which handles Arabic digit
     * conversion, non-digit stripping, leading-zero removal, and trunk-prefix stripping
     * (e.g. 9660559... -> 966559...). Falls back to PhoneUtils::normalize_phone()
     * for inputs that fail validation so callers always receive a usable string.
     *
     * Examples:
     *   normalize('+965XXXXXXXX')   -> '965XXXXXXXX'
     *   normalize('00965XXXXXXXX')  -> '965XXXXXXXX'
     *   normalize('+965 XXXX XXXX') -> '965XXXXXXXX'
     *   normalize(Arabic-Indic digits of 965XXXXXXXX) -> '965XXXXXXXX'
     *   (2026-11-05 11:24 +03: examples use the placeholder 965XXXXXXXX instead of a
     *   sample mobile number, sample numbers belong to real people, int-testnumber)
     *   normalize('9660559123456')  -> '966559123456'  (trunk 0 stripped)
     */
    public function normalize(string $phone): string
    {
        [, , $normalized] = PhoneUtils::validate_phone_input($phone);

        return $normalized ?? PhoneUtils::normalize_phone($phone);
    }

    /**
     * Find the country code prefix from a normalized phone number.
     * Tries 3-digit codes first, then 2-digit, then 1-digit (longest match wins).
     */
    public function findCountryCode(string $normalized): ?string
    {
        return PhoneUtils::find_country_code($normalized);
    }

    /**
     * Validate a normalized phone number against country-specific format rules.
     * Checks local number length and mobile starting digits.
     * Numbers with no matching country rules pass through (generic E.164 only).
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validatePhoneFormat(string $normalized): array
    {
        [$valid, $error] = PhoneUtils::validate_phone_format($normalized);

        return ['valid' => $valid, 'error' => $error];
    }

    /**
     * Verify that a normalized phone number is valid for sending.
     *
     * Delegates to PhoneUtils::validate_phone_input() for E.164 length check,
     * trunk-0 stripping, and country-specific format validation. Optionally
     * checks that the number starts with one of the provided coverage prefixes.
     *
     * @param  string  $normalizedPhone  Already-normalized phone (digits only).
     * @param  string[]  $coverage  Array of valid country prefixes (e.g. ['965', '966']).
     *                              Empty array skips coverage check.
     */
    public function verify(string $normalizedPhone, array $coverage = []): bool
    {
        [$valid] = PhoneUtils::validate_phone_input($normalizedPhone);

        if (! $valid) {
            return false;
        }

        if (empty($coverage)) {
            return true;
        }

        return $this->matchesCoverage($normalizedPhone, $coverage);
    }

    /**
     * Normalize and verify multiple phone numbers.
     *
     * @param  string[]  $phones  Raw phone numbers.
     * @param  string[]  $coverage  Coverage prefixes for filtering.
     * @return array{valid: string[], invalid: string[]}
     */
    public function normalizeMany(array $phones, array $coverage = []): array
    {
        $valid = [];
        $invalid = [];

        foreach ($phones as $phone) {
            $normalized = $this->normalize($phone);

            if ($this->verify($normalized, $coverage)) {
                $valid[] = $normalized;
            } else {
                $invalid[] = $phone;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Check if a normalized phone number starts with any of the given prefixes.
     *
     * Empty-string prefixes are skipped to prevent every number matching.
     *
     * @param  string[]  $coverage
     */
    private function matchesCoverage(string $normalizedPhone, array $coverage): bool
    {
        foreach ($coverage as $prefix) {
            $prefix = (string) $prefix;

            if ($prefix === '') {
                continue;
            }

            if (str_starts_with($normalizedPhone, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
