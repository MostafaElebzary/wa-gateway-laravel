<?php

namespace WaGateway\NotificationChannel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\ServiceProvider;
use WaGateway\NotificationChannel\Exceptions\CouldNotSendNotification;
use WaGateway\NotificationChannel\WaGatewayChannel;
use WaGateway\NotificationChannel\WaGatewayMessage;
use WaGateway\NotificationChannel\WaGatewayServiceProvider;

class Customer
{
    use Notifiable;

    public function __construct(public ?string $phone = '+201001234567') {}

    public function routeNotificationForWaGateway(): ?string
    {
        return $this->phone;
    }
}

class OrderShipped extends Notification
{
    public function __construct(private readonly WaGatewayMessage|string $message) {}

    public function via(object $notifiable): array
    {
        return [WaGatewayChannel::class];
    }

    public function toWaGateway(object $notifiable): WaGatewayMessage|string
    {
        return $this->message;
    }
}

class WaGatewayChannelTest extends TestCase
{
    private function accepted(): void
    {
        Http::fake(['gw.example.com/*' => Http::response(['success' => true, 'data' => ['message_id' => '01j9abc', 'status' => 'queued'], 'error' => null], 202)]);
    }

    public function test_notification_sends_to_the_notifiables_route_with_the_configured_token(): void
    {
        $this->accepted();

        (new Customer)->notify(new OrderShipped(WaGatewayMessage::create('Your order #1042 has shipped.')));

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://gw.example.com/api/v1/messages/text'
            && $r->hasHeader('Authorization', 'Bearer wai_default_token')
            && $r->data() === ['to' => '+201001234567', 'text' => 'Your order #1042 has shipped.']);
    }

    public function test_media_goes_to_its_endpoint_and_to_and_token_can_be_overridden(): void
    {
        $this->accepted();

        (new Customer)->notify(new OrderShipped(
            WaGatewayMessage::create()->document('https://x/i.pdf', 'Invoice.pdf', 'application/pdf', 'Your invoice')
                ->to('+201556667778')
                ->usingToken('wai_billing_line'),
        ));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://gw.example.com/api/v1/messages/document'
            && $r->hasHeader('Authorization', 'Bearer wai_billing_line')
            && $r['to'] === '+201556667778'
            && $r['filename'] === 'Invoice.pdf');
    }

    public function test_idempotency_key_is_sent_as_a_header_only_when_set(): void
    {
        $this->accepted();

        (new Customer)->notify(new OrderShipped(WaGatewayMessage::create('Shipped')->idempotencyKey('order-1042-shipped')));
        (new Customer)->notify(new OrderShipped(WaGatewayMessage::create('No key')));

        Http::assertSent(fn (Request $r) => $r['text'] === 'Shipped' && $r->hasHeader('Idempotency-Key', 'order-1042-shipped'));
        Http::assertSent(fn (Request $r) => $r['text'] === 'No key' && ! $r->hasHeader('Idempotency-Key'));
    }

    public function test_returns_the_gateway_reply_and_accepts_a_plain_string(): void
    {
        $this->accepted();

        $reply = app(WaGatewayChannel::class)->send(new Customer, new OrderShipped('plain text'));

        $this->assertSame(['message_id' => '01j9abc', 'status' => 'queued'], $reply);
        Http::assertSent(fn (Request $r) => $r['text'] === 'plain text');
    }

    public function test_on_demand_notifications_with_the_wa_gateway_alias(): void
    {
        $this->accepted();

        NotificationFacade::route('wa-gateway', '+201234567890')
            ->notify(new class extends Notification
            {
                public function via(object $notifiable): array
                {
                    return ['wa-gateway'];
                }

                public function toWaGateway(object $notifiable): WaGatewayMessage
                {
                    return WaGatewayMessage::create('Your code is 4821');
                }
            });

        Http::assertSent(fn (Request $r) => $r['to'] === '+201234567890' && $r['text'] === 'Your code is 4821');
    }

    public function test_no_recipient_means_nothing_is_sent(): void
    {
        Http::fake();

        $reply = app(WaGatewayChannel::class)->send(new Customer(phone: null), new OrderShipped('x'));

        $this->assertNull($reply);
        Http::assertNothingSent();
    }

    public function test_api_errors_throw_with_the_gateway_code(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false, 'data' => null,
            'error' => ['code' => 'instance_not_connected', 'message' => 'Instance is qr; connect it by scanning the QR code first.'],
        ], 409)]);

        try {
            (new Customer)->notify(new OrderShipped('x'));
            $this->fail('expected CouldNotSendNotification');
        } catch (CouldNotSendNotification $e) {
            $this->assertSame('instance_not_connected', $e->errorCode);
            $this->assertSame(409, $e->status);
            $this->assertStringContainsString('scanning the QR', $e->getMessage());
        }
    }

    public function test_unreachable_gateway_and_missing_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        try {
            (new Customer)->notify(new OrderShipped('x'));
            $this->fail('expected CouldNotSendNotification');
        } catch (CouldNotSendNotification $e) {
            $this->assertSame('unreachable', $e->errorCode);
        }

        config(['wa-gateway.token' => null]);
        $this->expectExceptionMessage('No WA Gateway token');
        (new Customer)->notify(new OrderShipped('x'));
    }

    public function test_config_is_merged_and_publishable(): void
    {
        $this->assertSame(15, config('wa-gateway.timeout'));
        $paths = ServiceProvider::pathsToPublish(WaGatewayServiceProvider::class, 'wa-gateway-config');
        $sources = array_map('realpath', array_keys($paths));
        $this->assertContains(realpath(dirname(__DIR__).'/config/wa-gateway.php'), $sources);
        $this->assertStringEndsWith('config/wa-gateway.php', array_values($paths)[0]);
    }
}
