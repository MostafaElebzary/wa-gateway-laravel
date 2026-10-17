<?php

namespace WaGateway\NotificationChannel;

use InvalidArgumentException;

/**
 * A WhatsApp message built in a notification's toWaGateway() method.
 *
 *     return WaGatewayMessage::create('Your order #1042 has shipped.');
 *     return WaGatewayMessage::create()->image('https://cdn.example.com/a.jpg', 'Your receipt');
 *     return WaGatewayMessage::create()->document($url, 'Invoice-1042.pdf', 'application/pdf');
 */
class WaGatewayMessage
{
    /** API endpoint suffix: text | image | video | audio | document | location */
    public string $type = 'text';

    /** @var array<string, mixed> */
    public array $payload = [];

    /** Recipient phone (international format); defaults to the notifiable's route. */
    public ?string $to = null;

    /** Instance token to send from; defaults to config('wa-gateway.token'). */
    public ?string $token = null;

    /** Sent as the Idempotency-Key header: retries of the same message are not sent twice. */
    public ?string $idempotencyKey = null;

    public function __construct(string $text = '')
    {
        if ($text !== '') {
            $this->text($text);
        }
    }

    public static function create(string $text = ''): static
    {
        return new static($text);
    }

    public function text(string $text): static
    {
        return $this->set('text', ['text' => $text]);
    }

    public function image(string $url, ?string $caption = null): static
    {
        return $this->set('image', ['url' => $url, 'caption' => $caption]);
    }

    public function video(string $url, ?string $caption = null): static
    {
        return $this->set('video', ['url' => $url, 'caption' => $caption]);
    }

    /** Use voiceNote: true to send as a voice note (OGG/Opus audio). */
    public function audio(string $url, bool $voiceNote = false): static
    {
        return $this->set('audio', ['url' => $url, 'voice_note' => $voiceNote]);
    }

    public function document(string $url, string $filename, string $mimetype, ?string $caption = null): static
    {
        return $this->set('document', ['url' => $url, 'filename' => $filename, 'mimetype' => $mimetype, 'caption' => $caption]);
    }

    public function location(float $lat, float $lng, ?string $name = null, ?string $address = null): static
    {
        return $this->set('location', ['lat' => $lat, 'lng' => $lng, 'name' => $name, 'address' => $address]);
    }

    /** Send to this number instead of the notifiable's routeNotificationForWaGateway(). */
    public function to(string $phone): static
    {
        $this->to = $phone;

        return $this;
    }

    /**
     * Make retries safe: the gateway sends a message with a given key only once (per number).
     * Use something unique to the event, e.g. "order-1042-shipped".
     */
    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /** Send from another WhatsApp number (its instance token). */
    public function usingToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Request body for POST /api/v1/messages/{type}.
     *
     * @return array<string, mixed>
     */
    public function toArray(string $to): array
    {
        if ($this->payload === []) {
            throw new InvalidArgumentException('WaGatewayMessage has no content: call text(), image(), document() …');
        }

        return ['to' => $to] + array_filter($this->payload, fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function set(string $type, array $payload): static
    {
        $this->type = $type;
        $this->payload = $payload;

        return $this;
    }
}
