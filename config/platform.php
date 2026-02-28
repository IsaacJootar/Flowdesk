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
];
