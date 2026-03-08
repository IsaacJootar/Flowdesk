<?php

return [
    'cache' => [
        'enabled' => filter_var(env('FLOWDESK_PERFORMANCE_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'dashboard_ttl_seconds' => (int) env('FLOWDESK_DASHBOARD_CACHE_TTL_SECONDS', 45),
        'reports_metrics_ttl_seconds' => (int) env('FLOWDESK_REPORTS_METRICS_CACHE_TTL_SECONDS', 60),
    ],
];

