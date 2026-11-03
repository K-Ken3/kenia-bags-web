<?php

return [
    'sandbox_enabled' => (bool) env('PAYMENT_SANDBOX_ENABLED', false),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    'require_webhook_signature' => env('PAYMENT_REQUIRE_WEBHOOK_SIGNATURE', false),
];
