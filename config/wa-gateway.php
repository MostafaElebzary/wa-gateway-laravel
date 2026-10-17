<?php

return [

    /*
    | Base URL of the WA Gateway API (without /api/v1).
    */
    'base_url' => env('WA_GATEWAY_URL', 'https://api.example.com'),

    /*
    | Instance token (wai_…) of the WhatsApp number that sends. A message can override it
    | with ->usingToken() to send from another number.
    */
    'token' => env('WA_GATEWAY_TOKEN'),

    /*
    | Seconds to wait for the API. Sending is asynchronous on the gateway side (202 Accepted),
    | so this only covers queuing the message, not delivering it.
    */
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 15),

];
