<?php

return [
    'rate_limits' => [
        // Centralize tenant throttles so production tuning is explicit and reviewable.
        'execution_webhooks_per_minute' => (int) env('FLOWDESK_RATE_LIMIT_EXECUTION_WEBHOOKS_PER_MINUTE', 60),
        'tenant_downloads_per_minute' => (int) env('FLOWDESK_RATE_LIMIT_TENANT_DOWNLOADS_PER_MINUTE', 120),
        'tenant_exports_per_minute' => (int) env('FLOWDESK_RATE_LIMIT_TENANT_EXPORTS_PER_MINUTE', 40),
    ],
];
