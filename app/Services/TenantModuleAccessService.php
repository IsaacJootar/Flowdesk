<?php

namespace App\Services;

use App\Models\User;

class TenantModuleAccessService
{
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
            'requests.index' => $this->moduleEnabled($user, 'requests'),
            'requests.communications' => $this->moduleEnabled($user, ['requests', 'communications']),
            'requests.reports' => $this->moduleEnabled($user, ['requests', 'reports']),
            'reports.index' => $this->moduleEnabled($user, 'reports'),
            'expenses.index' => $this->moduleEnabled($user, 'expenses'),
            'vendors.index', 'vendors.show', 'vendors.reports' => $this->moduleEnabled($user, 'vendors'),
            'budgets.index' => $this->moduleEnabled($user, 'budgets'),
            'assets.index', 'assets.reports' => $this->moduleEnabled($user, 'assets'),
            'approval-workflows.index', 'settings.request-configuration', 'settings.approval-timing-controls' => $this->moduleEnabled($user, 'requests'),
            'settings.communications' => $this->moduleEnabled($user, 'communications'),
            'settings.expense-controls' => $this->moduleEnabled($user, 'expenses'),
            'settings.vendor-controls' => $this->moduleEnabled($user, 'vendors'),
            'settings.asset-controls' => $this->moduleEnabled($user, 'assets'),
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
        ];

        if (! $user || ! $user->company_id) {
            return $defaults;
        }

        $user->loadMissing('company.featureEntitlements');
        $entitlements = $user->company?->featureEntitlements;

        if (! $entitlements) {
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
        ];
    }
}

