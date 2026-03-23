<?php

$errorTrackingEnvironments = array_values(array_filter(array_map(
    static fn (string $environment): string => trim($environment),
    explode(',', (string) env('FLOWDESK_ERROR_TRACKING_ENVIRONMENTS', 'production'))
)));

return [
    'correlation' => [
        'header' => env('FLOWDESK_CORRELATION_HEADER', 'X-Correlation-ID'),
    ],

    'error_tracking' => [
        'enabled' => filter_var(env('FLOWDESK_ERROR_TRACKING_ENABLED', true), FILTER_VALIDATE_BOOL),
        'log_channel' => env('FLOWDESK_ERROR_TRACKING_LOG_CHANNEL', 'error-tracking'),
        'webhook_url' => env('FLOWDESK_ERROR_TRACKING_WEBHOOK_URL', ''),
        'token' => env('FLOWDESK_ERROR_TRACKING_TOKEN', ''),
        'timeout_seconds' => (int) env('FLOWDESK_ERROR_TRACKING_TIMEOUT_SECONDS', 3),
        'environments' => $errorTrackingEnvironments !== [] ? $errorTrackingEnvironments : ['production'],
        'ignored_exceptions' => [
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ],
    ],

    'production_validation' => [
        'fail_fast' => filter_var(env('FLOWDESK_PRODUCTION_VALIDATION_FAIL_FAST', false), FILTER_VALIDATE_BOOL),
    ],

    'runtime' => [
        'scheduler_heartbeat_cache_key' => env('FLOWDESK_SCHEDULER_HEARTBEAT_CACHE_KEY', 'flowdesk:ops:scheduler-heartbeat-at'),
    ],
];
