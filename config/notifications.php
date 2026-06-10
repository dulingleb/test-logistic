<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider failure simulation
    |--------------------------------------------------------------------------
    |
    | Each provider stub rolls a random number per send. If the roll falls in
    | the permanent band -> PermanentProviderException (no retry). If it
    | falls in the transient band right after -> RuntimeException (retried).
    | Both rates are floats in [0, 1]; their sum must not exceed 1.
    |
    */

    'provider_failure_rate' => [
        'permanent' => (float) env('NOTIFICATIONS_PERMANENT_FAILURE_RATE', 0),
        'transient' => (float) env('NOTIFICATIONS_TRANSIENT_FAILURE_RATE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Synthetic provider latency
    |--------------------------------------------------------------------------
    |
    | Random sleep inside provider stubs between [min, max] milliseconds,
    | applied per send. Skip with max <= 0.
    |
    */

    'provider_delay_ms' => [
        'min' => (int) env('NOTIFICATIONS_PROVIDER_DELAY_MIN_MS', 0),
        'max' => (int) env('NOTIFICATIONS_PROVIDER_DELAY_MAX_MS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Async delivery callback (stub providers)
    |--------------------------------------------------------------------------
    |
    | When enabled, stub providers dispatch a delayed ConfirmDeliveryJob that
    | acts as the provider calling back into our system to confirm delivery
    | or report a delivery failure. failure_rate is the chance the simulated
    | callback flips the notification to Failed instead of Delivered.
    |
    */

    'delivery_callback' => [
        'enabled' => (bool) env('NOTIFICATIONS_DELIVERY_CALLBACK_ENABLED', false),
        'delay_seconds' => [
            'min' => (int) env('NOTIFICATIONS_DELIVERY_CALLBACK_DELAY_MIN', 1),
            'max' => (int) env('NOTIFICATIONS_DELIVERY_CALLBACK_DELAY_MAX', 10),
        ],
        'failure_rate' => (float) env('NOTIFICATIONS_DELIVERY_CALLBACK_FAILURE_RATE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dead-letter queue
    |--------------------------------------------------------------------------
    |
    | Once SendNotificationJob is exhausted (tries reached) the failed() hook
    | dispatches a marker payload onto this queue so operators can audit /
    | replay. No worker should be running on it in production.
    |
    */

    'dead_letter_queue' => env('NOTIFICATIONS_DLQ', 'notifications.dlq'),

    /*
    |--------------------------------------------------------------------------
    | Send job retry policy
    |--------------------------------------------------------------------------
    |
    | tries: total attempts including the first.
    | backoff: delays in seconds BETWEEN attempts; length must be tries - 1.
    | jitter: 0..1 fraction added/subtracted randomly to each backoff value.
    |
    */

    'send_job' => [
        'tries' => (int) env('NOTIFICATIONS_SEND_TRIES', 5),
        'backoff' => [1, 5, 30, 300],
        'jitter' => 0.25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook shared secret
    |--------------------------------------------------------------------------
    |
    | If set, inbound /api/notifications/webhooks/{provider} requests must
    | send the same value in the X-Webhook-Secret header. Empty string
    | disables the check (useful for local dev only).
    |
    */

    'webhook_secret' => env('NOTIFICATIONS_WEBHOOK_SECRET', ''),

];
