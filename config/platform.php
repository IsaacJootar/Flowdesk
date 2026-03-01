<?php

$internalCompanySlugs = array_values(array_filter(array_map(
    static fn (string $slug): string => strtolower(trim($slug)),
    explode(',', (string) env('PLATFORM_INTERNAL_COMPANY_SLUGS', 'sivon-limited,simplified-software-tools-for-businesses-organizations'))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Internal Company Slugs
    |--------------------------------------------------------------------------
    |
    | Companies listed here are treated as platform-internal and excluded from
    | external tenant counts/lists in the platform control center.
    |
    */
    'internal_company_slugs' => $internalCompanySlugs,

    /*
    |--------------------------------------------------------------------------
    | Billing Automation Defaults
    |--------------------------------------------------------------------------
    |
    | These defaults are used when a tenant subscription has no explicit
    | grace_until date and when deciding if overdue should auto-escalate to
    | suspended.
    |
    */
    'billing_default_grace_days' => (int) env('PLATFORM_BILLING_DEFAULT_GRACE_DAYS', 3),
    'billing_default_trial_days' => (int) env('PLATFORM_BILLING_DEFAULT_TRIAL_DAYS', 14),
    'billing_auto_suspend_after_days_overdue' => (int) env('PLATFORM_BILLING_AUTO_SUSPEND_AFTER_DAYS_OVERDUE', 14),
];

