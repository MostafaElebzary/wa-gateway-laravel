<?php

namespace WaGateway\NotificationChannel;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class WaGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wa-gateway.php', 'wa-gateway');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/wa-gateway.php' => config_path('wa-gateway.php'),
        ], 'wa-gateway-config');

        // Allows via() to return 'wa-gateway' as well as WaGatewayChannel::class.
        Notification::resolved(function (ChannelManager $service): void {
            $service->extend('wa-gateway', fn ($app) => $app->make(WaGatewayChannel::class));
        });
    }
}
