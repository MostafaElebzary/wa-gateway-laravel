<?php

namespace WaGateway\NotificationChannel\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WaGateway\NotificationChannel\WaGatewayMessage;

class WaGatewayMessageTest extends TestCase
{
    public function test_builds_the_api_body_for_every_type(): void
    {
        $to = '+201001234567';

        $this->assertSame(['to' => $to, 'text' => 'Hi'], WaGatewayMessage::create('Hi')->toArray($to));

        $m = WaGatewayMessage::create()->image('https://cdn.x/a.jpg', 'cap');
        $this->assertSame('image', $m->type);
        $this->assertSame(['to' => $to, 'url' => 'https://cdn.x/a.jpg', 'caption' => 'cap'], $m->toArray($to));

        // optional fields left null are not sent
        $this->assertSame(['to' => $to, 'url' => 'https://cdn.x/v.mp4'], WaGatewayMessage::create()->video('https://cdn.x/v.mp4')->toArray($to));

        $this->assertSame(['to' => $to, 'url' => 'https://cdn.x/a.ogg', 'voice_note' => true], WaGatewayMessage::create()->audio('https://cdn.x/a.ogg', voiceNote: true)->toArray($to));

        $this->assertSame(
            ['to' => $to, 'url' => 'https://x/i.pdf', 'filename' => 'Invoice.pdf', 'mimetype' => 'application/pdf'],
            WaGatewayMessage::create()->document('https://x/i.pdf', 'Invoice.pdf', 'application/pdf')->toArray($to),
        );

        $loc = WaGatewayMessage::create()->location(30.0444, 31.2357, 'Cairo');
        $this->assertSame('location', $loc->type);
        $this->assertSame(['to' => $to, 'lat' => 30.0444, 'lng' => 31.2357, 'name' => 'Cairo'], $loc->toArray($to));
    }

    public function test_last_content_call_wins_and_to_and_token_are_kept(): void
    {
        $m = WaGatewayMessage::create('draft')->to('+201112223334')->usingToken('wai_other')->image('https://x/a.png');

        $this->assertSame('image', $m->type);
        $this->assertSame('+201112223334', $m->to);
        $this->assertSame('wai_other', $m->token);
    }

    public function test_empty_message_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WaGatewayMessage::create()->toArray('+201001234567');
    }
}
