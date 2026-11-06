<?php

namespace KwtSMS\Laravel\Tests\Unit;

use KwtSMS\Laravel\Notifications\KwtSmsMessage;
use PHPUnit\Framework\TestCase;

class KwtSmsMessageTest extends TestCase
{
    public function test_message_content(): void
    {
        $msg = (new KwtSmsMessage)->content('Hello World');
        $this->assertSame('Hello World', $msg->getContent());
    }

    public function test_message_with_sender(): void
    {
        $msg = (new KwtSmsMessage)->sender('MY-SENDER');
        $this->assertSame('MY-SENDER', $msg->getSender());
    }

    public function test_message_with_event_type(): void
    {
        $msg = (new KwtSmsMessage)->eventType('order_placed');
        $this->assertSame('order_placed', $msg->getEventType());
    }

    public function test_message_content_static(): void
    {
        $msg = KwtSmsMessage::create()->content('Static content');
        $this->assertSame('Static content', $msg->getContent());
    }

    public function test_default_event_type(): void
    {
        $msg = new KwtSmsMessage;
        $this->assertSame('custom', $msg->getEventType());
    }

    public function test_fluent_chaining(): void
    {
        $msg = KwtSmsMessage::create()
            ->content('Test')
            ->sender('KWTSMS')
            ->eventType('otp');

        $this->assertSame('Test', $msg->getContent());
        $this->assertSame('KWTSMS', $msg->getSender());
        $this->assertSame('otp', $msg->getEventType());
    }
}
