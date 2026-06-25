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

    /**
     * Cap on stress-test cycles hydrated into the results page. That page re-fetches the
     * run (cycles included) on a 5s poll while it runs, so an unbounded cycle array is
     * re-serialized every tick; only the most recent cycles are kept, with a truncation
     * flag for the UI. See FE-03.
     */
    'stress_result_max_cycles' => (int) env('STRESS_RESULT_MAX_CYCLES', 200),

    /**
     * Retention windows (in days) for append-only observability tables. These rows
     * are pruned by the scheduled `model:prune` run; they are diagnostics, not CRM
     * records, so older data is dropped once it is past its analysis window. A value
     * of 0 disables pruning for that table. See GROW-4.
     */
    'retention' => [
        'interaction_events_days' => (int) env('RETENTION_INTERACTION_EVENTS_DAYS', 90),
        'ai_runs_days' => (int) env('RETENTION_AI_RUNS_DAYS', 90),
    ],
];
