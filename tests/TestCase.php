<?php

namespace WaGateway\NotificationChannel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WaGateway\NotificationChannel\WaGatewayServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WaGatewayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('wa-gateway.base_url', 'https://gw.example.com');
        $app['config']->set('wa-gateway.token', 'wai_default_token');
    }
}
