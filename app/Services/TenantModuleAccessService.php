<?php

namespace App\Services;

use App\Models\User;

class TenantModuleAccessService
{
    public function __construct(
        private readonly TenantPlanDefaultsService $tenantPlanDefaultsService
    ) {
    }

    /**
     * @param  string|array<int, string>  $modules
     */
    public function moduleEnabled(?User $user, string|array $modules): bool
    {
        if (! $user) {
            return false;
        }

        $requiredModules = is_array($modules) ? $modules : [$modules];
        $entitlements = $this->entitlementsForUser($user);

        foreach ($requiredModules as $module) {
            $moduleKey = strtolower(trim((string) $module));
            if ($moduleKey === '') {
                continue;
            }

            if (($entitlements[$moduleKey] ?? true) !== true) {
                return false;
            }
        }

        return true;
    }

    public function routeEnabled(?User $user, string $routeName): bool
    {
        if (! $user) {
            return false;
        }

        $route = trim($routeName);
        if ($route === '') {
            return true;
        }

        return match ($route) {
            'requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help' => $this->moduleEnabled($user, 'requests'),
            'requests.communications', 'requests.communications-help' => $this->moduleEnabled($user, ['requests', 'communications']),
            'requests.reports' => $this->moduleEnabled($user, ['requests', 'reports']),
            'reports.index' => $this->moduleEnabled($user, 'reports'),
            'expenses.index' => $this->moduleEnabled($user, 'expenses'),
            'vendors.index', 'vendors.show', 'vendors.reports' => $this->moduleEnabled($user, 'vendors'),
            'budgets.index' => $this->moduleEnabled($user, 'budgets'),
            'assets.index', 'assets.reports' => $this->moduleEnabled($user, 'assets'),
            'procurement.release-desk', 'procurement.release-help', 'procurement.orders', 'procurement.receipts', 'procurement.match-exceptions' => $this->moduleEnabled($user, 'procurement'),
            'approval-workflows.index', 'settings.request-configuration', 'settings.approval-timing-controls' => $this->moduleEnabled($user, 'requests'),
            'settings.communications' => $this->moduleEnabled($user, 'communications'),
            'settings.expense-controls' => $this->moduleEnabled($user, 'expenses'),
            'settings.vendor-controls' => $this->moduleEnabled($user, 'vendors'),
            'settings.asset-controls' => $this->moduleEnabled($user, 'assets'),
            'settings.procurement-controls' => $this->moduleEnabled($user, 'procurement'),
            'settings.treasury-controls' => $this->moduleEnabled($user, 'treasury'),
            'settings.payments-rails' => $this->moduleEnabled($user, 'fintech'),
            'treasury.reconciliation', 'treasury.reconciliation-help', 'treasury.reconciliation-exceptions', 'treasury.payment-runs', 'treasury.cash-position' => $this->moduleEnabled($user, 'treasury'),
            default => true,
        };
    }

    /**
     * @return array<string, bool>
     */
    public function entitlementsForUser(?User $user): array
    {
        $defaults = [
            'requests' => true,
            'expenses' => true,
            'vendors' => true,
            'budgets' => true,
            'assets' => true,
            'reports' => true,
            'communications' => true,
            'ai' => false,
            'fintech' => false,
            'procurement' => false,
            'treasury' => false,
        ];

        if (! $user || ! $user->company_id) {
            return $defaults;
        }

        $user->loadMissing('company.featureEntitlements', 'company.subscription');
        $entitlements = $user->company?->featureEntitlements;

        if (! $entitlements) {
            $planCode = $user->company?->subscription?->plan_code;
            if ($planCode) {
                $planDefaults = $this->tenantPlanDefaultsService->defaultsForPlan((string) $planCode);

                return $planDefaults['entitlements'];
            }

            return $defaults;
        }

        return [
            'requests' => (bool) $entitlements->requests_enabled,
            'expenses' => (bool) $entitlements->expenses_enabled,
            'vendors' => (bool) $entitlements->vendors_enabled,
            'budgets' => (bool) $entitlements->budgets_enabled,
            'assets' => (bool) $entitlements->assets_enabled,
            'reports' => (bool) $entitlements->reports_enabled,
            'communications' => (bool) $entitlements->communications_enabled,
            'ai' => (bool) $entitlements->ai_enabled,
            'fintech' => (bool) $entitlements->fintech_enabled,
            'procurement' => (bool) $entitlements->procurement_enabled,
            'treasury' => (bool) $entitlements->treasury_enabled,
        ];
    }
}






