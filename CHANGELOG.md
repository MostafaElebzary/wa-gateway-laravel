# Changelog

## Unreleased

- CI: test Laravel 11 despite Composer's security-advisory block (Laravel 11 is out of
  security support).

## 1.0.0 (2026-10-17)

First release.

- Notification channel `WaGatewayChannel` (alias `wa-gateway`) for Laravel 11, 12 and 13.
- `WaGatewayMessage`: text, image, video, audio / voice note, document and location.
- Recipient from `routeNotificationForWaGateway()`, on-demand routes, or `->to()`.
- `->usingToken()` to send from another number; `->idempotencyKey()` for safe retries.
- `CouldNotSendNotification` with the API's error code; the gateway's reply
  (`message_id`, `status`) is returned so `NotificationSent` listeners can store it.
