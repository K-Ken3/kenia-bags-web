<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operational Monitoring
    |--------------------------------------------------------------------------
    |
    | Controls the in-application monitoring layer: persisted operational
    | events, alert webhook delivery, health probes and the ops:monitor
    | scheduled report. Disabled outside production unless explicitly enabled
    | so local errors never page an operator.
    |
    */

    'enabled' => filter_var(
        env('MONITORING_ENABLED', env('APP_ENV') === 'production' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    | Slack-compatible incoming webhook URL (Slack, Mattermost, or any endpoint
    | that accepts a JSON body with a "text" field). Leave blank to only write
    | events and logs.
    */
    'alert_webhook' => env('MONITORING_ALERT_WEBHOOK'),

    'alert_min_seconds' => (int) env('MONITORING_ALERT_MIN_SECONDS', 300),

    'retention_days' => (int) env('MONITORING_RETENTION_DAYS', 30),

    'thresholds' => [
        'failed_jobs' => (int) env('MONITORING_FAILED_JOBS_THRESHOLD', 5),
        'stuck_payments_minutes' => (int) env('MONITORING_STUCK_PAYMENTS_MINUTES', 20),
        'stuck_payments_count' => (int) env('MONITORING_STUCK_PAYMENTS_COUNT', 3),
        'webhook_failures' => (int) env('MONITORING_WEBHOOK_FAILURES_THRESHOLD', 5),
        'admin_failures' => (int) env('MONITORING_ADMIN_FAILURES_THRESHOLD', 10),
    ],

    'window_minutes' => (int) env('MONITORING_WINDOW_MINUTES', 15),

];
