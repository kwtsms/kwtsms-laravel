<?php

namespace KwtSMS\Laravel\Tests\Feature;

use KwtSMS\Laravel\Services\PhoneNormalizer;
use KwtSMS\Laravel\Tests\TestCase;

/**
 * 2026-11-05 11:25 +03: mock numbers use 99912345678 (country code 999 is unassigned,
 * nobody holds it; PhoneUtils applies only generic 7-15 digit rules to it) instead of a
 * sample Kuwait mobile that belongs to a real person; coverage fixtures add '999' as
 * test data only, int-testnumber.
 */
class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = app(PhoneNormalizer::class);
    }

    public function test_normalizes_plus_prefix(): void
    {
        $this->assertSame('99912345678', $this->normalizer->normalize('+99912345678'));
    }

    public function test_normalizes_double_zero_prefix(): void
    {
        $this->assertSame('99912345678', $this->normalizer->normalize('0099912345678'));
    }

    public function test_normalizes_spaces(): void
    {
        $this->assertSame('99912345678', $this->normalizer->normalize('999 1234 5678'));
    }

    public function test_normalizes_dashes(): void
    {
        $this->assertSame('99912345678', $this->normalizer->normalize('999-1234-5678'));
    }

    public function test_normalizes_arabic_digits(): void
    {
        // Arabic-Indic digits for 99912345678
        $this->assertSame('99912345678', $this->normalizer->normalize('٩٩٩١٢٣٤٥٦٧٨'));
    }

    public function test_strips_trunk_zero_from_saudi_local_number(): void
    {
        // 966 + 0559... -> 966559... (trunk prefix 0 stripped)
        $this->assertSame('966559123456', $this->normalizer->normalize('9660559123456'));
    }

    public function test_strips_trunk_zero_with_plus_prefix(): void
    {
        $this->assertSame('966559123456', $this->normalizer->normalize('+9660559123456'));
    }

    public function test_valid_number_in_coverage(): void
    {
        // 99912345678 starts with '999', which is in the test coverage list
        $result = $this->normalizer->normalizeMany(['99912345678'], ['965', '999']);
        $this->assertContains('99912345678', $result['valid']);
        $this->assertEmpty($result['invalid']);
    }

    public function test_number_out_of_coverage(): void
    {
        // 97112345678 starts with '971', not in ['965'] coverage
        $result = $this->normalizer->normalizeMany(['97112345678'], ['965']);
        $this->assertEmpty($result['valid']);
        $this->assertNotEmpty($result['invalid']);
    }
}
