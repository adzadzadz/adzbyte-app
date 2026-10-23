<?php

return [
    'idempotency' => [
        'ttl_hours' => (int) env('API_IDEMPOTENCY_TTL_HOURS', 24),
        'pending_timeout_seconds' => (int) env('API_IDEMPOTENCY_PENDING_TIMEOUT_SECONDS', 900),
        'lock_seconds' => (int) env('API_IDEMPOTENCY_LOCK_SECONDS', 300),
        'lock_wait_seconds' => (int) env('API_IDEMPOTENCY_LOCK_WAIT_SECONDS', 5),
    ],
];
