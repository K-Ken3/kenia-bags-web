<?php

return [
    'sandbox_enabled' => (bool) env('PAYMENT_SANDBOX_ENABLED', false),
    // The generic webhook is disabled unless both this flag and a secret are configured.
    'webhook_enabled' => (bool) env('PAYMENT_WEBHOOK_ENABLED', false),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

    'xentripay' => [
        'enabled' => (bool) env('XENTRIPAY_ENABLED', false),
        // Use https://merchant.test.xentripay.com for sandbox keys.
        'base_url' => env('XENTRIPAY_BASE_URL', 'https://merchant.test.xentripay.com'),
        'api_key' => env('XENTRIPAY_API_KEY'),
        'webhook_secret' => env('XENTRIPAY_WEBHOOK_SECRET'),
        // true: the fee is deducted from the customer amount. false: the fee is added on top.
        'charges_included' => (bool) env('XENTRIPAY_CHARGES_INCLUDED', true),
    ],
];
