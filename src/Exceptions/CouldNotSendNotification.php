<?php

namespace WaGateway\NotificationChannel\Exceptions;

use RuntimeException;

/**
 * The gateway refused or could not queue the message. $errorCode is the API's
 * error.code (e.g. instance_not_connected, validation_failed, rate_limited).
 */
class CouldNotSendNotification extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }

    public static function missingToken(): self
    {
        return new self('No WA Gateway token: set WA_GATEWAY_TOKEN or call ->usingToken().', 'missing_token');
    }

    public static function apiError(int $status, ?string $code, ?string $message): self
    {
        return new self('WA Gateway rejected the message: '.($message ?: "HTTP {$status}"), $code, $status);
    }

    public static function unreachable(string $reason): self
    {
        return new self('WA Gateway is unreachable: '.$reason, 'unreachable');
    }
}
