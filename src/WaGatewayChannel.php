<?php

namespace WaGateway\NotificationChannel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Notifications\Notification;
use WaGateway\NotificationChannel\Exceptions\CouldNotSendNotification;

/**
 * Notification channel: return [WaGatewayChannel::class] (or 'wa-gateway') from via(), and
 * implement toWaGateway($notifiable): WaGatewayMessage on the notification.
 *
 * The recipient is $message->to, or else $notifiable->routeNotificationFor('wa-gateway')
 * (routeNotificationForWaGateway() on the model). With no recipient nothing is sent.
 */
class WaGatewayChannel
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @return array{message_id: string, status: string}|null the gateway's reply, null if skipped
     *
     * @throws CouldNotSendNotification
     */
    public function send(mixed $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toWaGateway')) {
            throw new CouldNotSendNotification(get_class($notification).' must define toWaGateway($notifiable).', 'missing_method');
        }

        $message = $notification->toWaGateway($notifiable);
        if (is_string($message)) {
            $message = WaGatewayMessage::create($message);
        }

        $to = $message->to ?? (method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('wa-gateway', $notification)
            : null);
        if (! is_string($to) || trim($to) === '') {
            return null;
        }

        $token = $message->token ?? config('wa-gateway.token');
        if (! is_string($token) || $token === '') {
            throw CouldNotSendNotification::missingToken();
        }

        try {
            $response = $this->http
                ->withToken($token)
                ->withHeaders(array_filter(['Idempotency-Key' => $message->idempotencyKey]))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('wa-gateway.timeout', 15))
                ->post(rtrim((string) config('wa-gateway.base_url'), '/').'/api/v1/messages/'.$message->type, $message->toArray($to));
        } catch (ConnectionException $e) {
            throw CouldNotSendNotification::unreachable($e->getMessage());
        }

        $json = $response->json();
        if (! $response->successful() || ($json['success'] ?? false) !== true) {
            throw CouldNotSendNotification::apiError($response->status(), $json['error']['code'] ?? null, $json['error']['message'] ?? null);
        }

        return $json['data'];
    }
}
