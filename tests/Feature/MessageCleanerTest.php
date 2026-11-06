<?php

namespace KwtSMS\Laravel\Tests\Feature;

use KwtSMS\Laravel\Tests\TestCase;
use KwtSMS\MessageUtils;

class MessageCleanerTest extends TestCase
{
    public function test_strips_emoji(): void
    {
        $result = MessageUtils::clean_message('Hello 🎉 World');
        $this->assertStringNotContainsString('🎉', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    public function test_strips_html(): void
    {
        $result = MessageUtils::clean_message('<b>Hello</b>');
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function test_strips_zero_width_space(): void
    {
        // Zero-width space U+200B
        $result = MessageUtils::clean_message("Hello\u{200B}World");
        $this->assertStringNotContainsString("\u{200B}", $result);
    }

    public function test_converts_arabic_digits_in_message(): void
    {
        // Arabic-Indic digits ١٢٣٤٥ should become 12345
        $result = MessageUtils::clean_message('OTP: ١٢٣٤٥');
        $this->assertStringContainsString('12345', $result);
    }
}
