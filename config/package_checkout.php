<?php

return [
    'enabled' => (bool) env('PACKAGE_CHECKOUT_ENABLED', false),
    'api_token' => env('PACKAGE_CHECKOUT_API_TOKEN'),
    'mode' => env('QRPH_PAYMONGO_MODE', 'test'),
    'secret_key' => env('QRPH_PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('QRPH_PAYMONGO_WEBHOOK_SECRET'),
    'version' => '2026-10-22',
    'pickleball_ends_at' => '2026-12-01T00:00:00+08:00',
    'qr_seconds' => 1800,
    'offers' => [
        'starter' => ['name' => 'Starter', 'amount_minor' => 77700],
        'business' => ['name' => 'Business', 'amount_minor' => 349900],
        'growth' => ['name' => 'Growth', 'amount_minor' => 649900],
        'pickleball' => ['name' => 'Pickleball', 'amount_minor' => 250000],
    ],
];
