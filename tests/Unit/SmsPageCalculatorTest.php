<?php

namespace KwtSMS\Laravel\Tests\Unit;

use KwtSMS\Laravel\Services\SmsSender;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SmsPageCalculatorTest extends TestCase
{
    private SmsSender $sender;

    private ReflectionMethod $calculateSmsPages;

    protected function setUp(): void
    {
        parent::setUp();

        // SmsSender constructor calls config() which requires Laravel app.
        // Use reflection to create instance without constructor.
        $reflection = new \ReflectionClass(SmsSender::class);
        $this->sender = $reflection->newInstanceWithoutConstructor();

        $this->calculateSmsPages = new ReflectionMethod($this->sender, 'calculateSmsPages');
        $this->calculateSmsPages->setAccessible(true);
    }

    private function calculatePages(string $message): int
    {
        return $this->calculateSmsPages->invoke($this->sender, $message);
    }

    public function test_single_page_gsm7(): void
    {
        $this->assertSame(1, $this->calculatePages('Hello World'));
    }

    public function test_single_page_gsm7_at_160_chars(): void
    {
        $this->assertSame(1, $this->calculatePages(str_repeat('A', 160)));
    }

    public function test_two_pages_gsm7_at_161_chars(): void
    {
        $this->assertSame(2, $this->calculatePages(str_repeat('A', 161)));
    }

    public function test_two_pages_gsm7_at_306_chars(): void
    {
        $this->assertSame(2, $this->calculatePages(str_repeat('A', 306)));
    }

    public function test_three_pages_gsm7_at_307_chars(): void
    {
        $this->assertSame(3, $this->calculatePages(str_repeat('A', 307)));
    }

    public function test_gsm7_extended_chars_count_as_two(): void
    {
        // 159 regular chars + 1 extended char (counts as 2) = 161 effective = 2 pages
        $this->assertSame(2, $this->calculatePages(str_repeat('A', 159).'{'));
    }

    public function test_single_page_ucs2(): void
    {
        $this->assertSame(1, $this->calculatePages('مرحبا'));
    }

    public function test_single_page_ucs2_at_70_chars(): void
    {
        $this->assertSame(1, $this->calculatePages(str_repeat('م', 70)));
    }

    public function test_two_pages_ucs2_at_71_chars(): void
    {
        $this->assertSame(2, $this->calculatePages(str_repeat('م', 71)));
    }

    public function test_two_pages_ucs2_at_134_chars(): void
    {
        $this->assertSame(2, $this->calculatePages(str_repeat('م', 134)));
    }

    public function test_three_pages_ucs2_at_135_chars(): void
    {
        $this->assertSame(3, $this->calculatePages(str_repeat('م', 135)));
    }

    public function test_mixed_arabic_english_uses_ucs2(): void
    {
        // Any Arabic char forces UCS-2 for the whole message
        $this->assertSame(1, $this->calculatePages(str_repeat('A', 69).'م')); // 70 chars UCS-2 = 1 page
    }

    public function test_seven_pages_max_gsm7(): void
    {
        // 7 pages GSM-7 multipart = 7 * 153 = 1071 chars
        $this->assertSame(7, $this->calculatePages(str_repeat('A', 1071)));
    }

    public function test_eight_pages_exceeds_max(): void
    {
        // 8 pages = 1072+ chars, should return 8 (caller checks > 7)
        $this->assertSame(8, $this->calculatePages(str_repeat('A', 1072)));
    }
}
