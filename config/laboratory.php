<?php

return [
    'retry' => [
        'max_attempts' => (int) env('RETRY_MAX_ATTEMPTS', 3),
        'business_hours_start' => env('RETRY_BUSINESS_HOURS_START', '08:00'),
        'business_hours_end' => env('RETRY_BUSINESS_HOURS_END', '18:00'),
        'backoff_minutes' => array_map('intval', explode(',', env('RETRY_BACKOFF_MINUTES', '15,60,240'))),
    ],

    'langfuse' => [
        'enabled' => env('LANGFUSE_ENABLED', false),
        'public_key' => env('LANGFUSE_PUBLIC_KEY'),
        'secret_key' => env('LANGFUSE_SECRET_KEY'),
        'host' => env('LANGFUSE_HOST', 'https://cloud.langfuse.com'),
        /** Full URL for the "Open Langfuse" button in Laboratory (e.g. https://cloud.langfuse.com/project/your-id/traces). */
        'dashboard_url' => env('LANGFUSE_DASHBOARD_URL'),
    ],

    'alerting' => [
        'enabled' => env('ALERT_ENABLED', false),
        'channel' => env('ALERT_CHANNEL', 'slack'),
        'slack_webhook' => env('SLACK_ALERT_WEBHOOK_URL'),
        'thresholds' => [
            'error_rate_percent' => 5,
            'queue_backlog' => 100,
            'response_time_seconds' => 30,
            'failed_retries_hourly' => 10,
        ],
    ],

    'stress_test_timeout' => (int) env('STRESS_TEST_TIMEOUT', 3600),
];
