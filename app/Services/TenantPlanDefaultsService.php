<?php

namespace App\Services;

class TenantPlanDefaultsService
{
    /**
     * @return array{seat_limit:int|null,entitlements:array<string,bool>}
     */
    public function defaultsForPlan(?string $planCode): array
    {
        $normalized = strtolower(trim((string) $planCode));
        $plans = (array) config('tenant_plans.plans', []);
        $fallback = strtolower((string) config('tenant_plans.default_plan', 'pilot'));
        $resolved = $normalized !== '' && array_key_exists($normalized, $plans)
            ? $normalized
            : $fallback;

        $plan = (array) ($plans[$resolved] ?? []);
        $entitlements = (array) ($plan['entitlements'] ?? []);

        return [
            'seat_limit' => isset($plan['default_seat_limit']) && $plan['default_seat_limit'] !== null
                ? (int) $plan['default_seat_limit']
                : null,
            'entitlements' => [
                'requests' => (bool) ($entitlements['requests'] ?? true),
                'expenses' => (bool) ($entitlements['expenses'] ?? true),
                'vendors' => (bool) ($entitlements['vendors'] ?? true),
                'budgets' => (bool) ($entitlements['budgets'] ?? true),
                'assets' => (bool) ($entitlements['assets'] ?? true),
                'reports' => (bool) ($entitlements['reports'] ?? true),
                'communications' => (bool) ($entitlements['communications'] ?? true),
                'ai' => (bool) ($entitlements['ai'] ?? false),
                'fintech' => (bool) ($entitlements['fintech'] ?? false),
                'procurement' => (bool) ($entitlements['procurement'] ?? false),
                'treasury' => (bool) ($entitlements['treasury'] ?? false),
            ],
        ];
    }

    /**
     * @return array<string,bool>
     */
    public function formEntitlementsForPlan(?string $planCode): array
    {
        $defaults = $this->defaultsForPlan($planCode);
        $modules = $defaults['entitlements'];

        return [
            'requests_enabled' => (bool) ($modules['requests'] ?? true),
            'expenses_enabled' => (bool) ($modules['expenses'] ?? true),
            'vendors_enabled' => (bool) ($modules['vendors'] ?? true),
            'budgets_enabled' => (bool) ($modules['budgets'] ?? true),
            'assets_enabled' => (bool) ($modules['assets'] ?? true),
            'reports_enabled' => (bool) ($modules['reports'] ?? true),
            'communications_enabled' => (bool) ($modules['communications'] ?? true),
            'ai_enabled' => (bool) ($modules['ai'] ?? false),
            'fintech_enabled' => (bool) ($modules['fintech'] ?? false),
            'procurement_enabled' => (bool) ($modules['procurement'] ?? false),
            'treasury_enabled' => (bool) ($modules['treasury'] ?? false),
        ];
    }
}
