# WA Gateway notification channel for Laravel

Send WhatsApp messages from your Laravel app with Laravel's notifications, through the WA Gateway API.

```php
$order->customer->notify(new OrderShipped($order));
```

Works with Laravel 11, 12 and 13 (PHP 8.2+).

## Install

```bash
composer require wa-gateway/laravel-notification-channel
```

> Not on Packagist yet? Add the repository to your `composer.json` first, then run the same command:
>
> ```json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/MostafaElebzary/wa-gateway-laravel" }
> ]
> ```

Laravel discovers the service provider automatically. To customise the config file:

```bash
php artisan vendor:publish --tag=wa-gateway-config
```

## Configure

Add to your `.env`:

```dotenv
WA_GATEWAY_URL=https://api.example.com   # the gateway base URL, without /api/v1
WA_GATEWAY_TOKEN=wai_...                 # the instance token of the number that sends
WA_GATEWAY_TIMEOUT=15                    # optional, seconds
```

The instance token is shown once when you create a number (dashboard → Numbers → Add number, or `POST /api/v1/instances`). Lost it? Regenerate it from the dashboard.

## Tell Laravel where to send

Add `routeNotificationForWaGateway()` to your notifiable model. Return the phone in international format: `+201001234567`, `00201001234567` and `201001234567` are all accepted. Return `null` to skip WhatsApp for that user.

```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForWaGateway(): ?string
    {
        return $this->whatsapp_opt_in ? $this->phone : null;
    }
}
```

## Write a notification

Return `WaGatewayChannel::class` (or the alias `'wa-gateway'`) from `via()`, and build the message in `toWaGateway()`:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use WaGateway\NotificationChannel\WaGatewayChannel;
use WaGateway\NotificationChannel\WaGatewayMessage;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Order $order) {}

    public function via(object $notifiable): array
    {
        return [WaGatewayChannel::class, 'mail'];
    }

    public function toWaGateway(object $notifiable): WaGatewayMessage
    {
        return WaGatewayMessage::create("Hi {$notifiable->name}, order #{$this->order->number} has shipped.\nTrack it: {$this->order->tracking_url}");
    }
}
```

`toWaGateway()` may also return a plain string for a text message.

### Message types

```php
WaGatewayMessage::create('Plain text, line breaks kept.');

WaGatewayMessage::create()->image('https://cdn.example.com/receipt.jpg', 'Your receipt');

WaGatewayMessage::create()->document(
    'https://cdn.example.com/invoices/1042.pdf',
    'Invoice-1042.pdf',          // file name the customer sees
    'application/pdf',
    'Your invoice for October',  // optional caption
);

WaGatewayMessage::create()->video('https://cdn.example.com/setup.mp4', 'How to set it up');

WaGatewayMessage::create()->audio('https://cdn.example.com/welcome.ogg', voiceNote: true); // OGG/Opus for voice notes

WaGatewayMessage::create()->location(30.0444, 31.2357, 'Cairo Store', '12 Tahrir Square');
```

Media is downloaded by the gateway from the URL you give: it must be a public `http(s)` URL, up to 16 MB.

### Override the recipient or the sending number

```php
WaGatewayMessage::create('Your code is 4821')
    ->to('+201112223334')                       // instead of routeNotificationForWaGateway()
    ->usingToken(config('services.wa.billing')); // send from another of your numbers
```

### Safe retries (idempotency)

If your queue retries a notification after a timeout, the same WhatsApp message could be sent twice. Give the message a key that is unique to the event, and the gateway sends it only once, however many times the request is retried:

```php
WaGatewayMessage::create("Order #{$order->number} has shipped.")
    ->idempotencyKey("order-{$order->id}-shipped");
```

### On-demand notifications

No model needed:

```php
use Illuminate\Support\Facades\Notification;

Notification::route('wa-gateway', '+201001234567')->notify(new LoginCode($code));
```

## Delivery, errors and retries

- Sending is **asynchronous**. The gateway queues the message and answers immediately, then sends it a few seconds later. It deliberately paces messages to protect your number. Delivery and read receipts arrive on your webhook (`message.status`). See the API docs.
- If the gateway refuses the message, the channel throws `WaGateway\NotificationChannel\Exceptions\CouldNotSendNotification`. Its `$errorCode` is the API's code, for example:
  - `instance_not_connected`: the number is not paired; scan the QR code in the dashboard.
  - `validation_failed`: for example a national phone format like `010…`; use `+20…`.
  - `rate_limited`: more than 60 requests per minute for this number.
  - `account_inactive`: subscription expired or account suspended.
  - `unreachable`: network problem.
- **Queue your notifications** (`implements ShouldQueue`), so a temporary error is retried by your queue worker instead of failing the request. To stop retrying errors that will not fix themselves:

```php
public function failed(\Throwable $e): void
{
    // e.g. log $e->errorCode === 'validation_failed' for this user and skip WhatsApp next time
}
```

## Good practice

WhatsApp bans numbers that send unwanted messages. Only message people who opted in, keep messages relevant, and don't send bulk marketing from a new number: new numbers have a reduced daily limit for their first 7 days.

## Testing your app

Use Laravel's fake as usual:

```php
Notification::fake();
// ...
Notification::assertSentTo($user, OrderShipped::class);
```

## Developing this package

```bash
composer install
composer test
```
