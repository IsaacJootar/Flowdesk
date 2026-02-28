<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

class NavAccessService
{
    public function __construct(
        private readonly TenantModuleAccessService $tenantModuleAccessService,
    ) {
    }

    /**
     * @return array{
     *   items: array<int, array{route: string, pattern: array<int, string>, label: string}>,
     *   show_reports_placeholder: bool
     * }
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [
                'items' => [],
                'show_reports_placeholder' => false,
            ];
        }

        if (app(PlatformAccessService::class)->isPlatformOperator($user)) {
            return [
                'items' => $this->platformItems(),
                'show_reports_placeholder' => false,
            ];
        }

        $role = (string) $user->role;
        $items = match ($role) {
            UserRole::Owner->value => $this->ownerItems(),
            UserRole::Finance->value => $this->financeItems(),
            UserRole::Manager->value => $this->managerItems(),
            UserRole::Auditor->value => $this->auditorItems(),
            default => $this->staffItems(),
        };

        // Tenant entitlements are enforced at nav layer to avoid showing disabled modules.
        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => $this->tenantModuleAccessService->routeEnabled($user, (string) ($item['route'] ?? ''))
        ));

        // Expense nav is policy-driven so staff visibility follows Expense Controls.
        $canAccessExpenses = in_array($role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true) || app(ExpensePolicyResolver::class)->canCreateAny($user);

        if (
            $this->tenantModuleAccessService->moduleEnabled($user, 'expenses')
            && $canAccessExpenses
            && ! $this->containsRoute($items, 'expenses.index')
        ) {
            $this->insertAfter($items, 'requests.reports', [
                'route' => 'expenses.index',
                'pattern' => ['expenses.*'],
                'label' => 'Expenses',
            ]);
        }

        return [
            'items' => $items,
            'show_reports_placeholder' => false,
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function ownerItems(): array
    {
        return [
            ['route' => 'dashboard', 'pattern' => ['dashboard*'], 'label' => 'Dashboard'],
            ['route' => 'reports.index', 'pattern' => ['reports.index'], 'label' => 'Reports'],
            ['route' => 'requests.index', 'pattern' => ['requests.index'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'vendors.index', 'pattern' => ['vendors.index', 'vendors.show', 'vendors.reports'], 'label' => 'Manage Vendors'],
            ['route' => 'budgets.index', 'pattern' => ['budgets.*'], 'label' => 'Budgets'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
            ['route' => 'departments.index', 'pattern' => ['departments.*'], 'label' => 'Departments'],
            ['route' => 'team.index', 'pattern' => ['team.*'], 'label' => 'Team'],
            ['route' => 'approval-workflows.index', 'pattern' => ['approval-workflows.*'], 'label' => 'Approval Workflows'],
            ['route' => 'settings.communications', 'pattern' => ['settings.communications'], 'label' => 'Communications'],
            ['route' => 'settings.request-configuration', 'pattern' => ['settings.request-configuration'], 'label' => 'Request Configuration'],
            ['route' => 'settings.approval-timing-controls', 'pattern' => ['settings.approval-timing-controls'], 'label' => 'Approval Timing Controls'],
            ['route' => 'settings.expense-controls', 'pattern' => ['settings.expense-controls'], 'label' => 'Expense Controls'],
            ['route' => 'settings.asset-controls', 'pattern' => ['settings.asset-controls'], 'label' => 'Asset Controls'],
            ['route' => 'settings.vendor-controls', 'pattern' => ['settings.vendor-controls'], 'label' => 'Vendor Controls'],
            ['route' => 'settings.index', 'pattern' => ['settings.index'], 'label' => 'Settings'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function financeItems(): array
    {
        return [
            ['route' => 'dashboard', 'pattern' => ['dashboard*'], 'label' => 'Dashboard'],
            ['route' => 'reports.index', 'pattern' => ['reports.index'], 'label' => 'Reports'],
            ['route' => 'requests.index', 'pattern' => ['requests.index'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'vendors.index', 'pattern' => ['vendors.index', 'vendors.show', 'vendors.reports'], 'label' => 'Vendors'],
            ['route' => 'budgets.index', 'pattern' => ['budgets.*'], 'label' => 'Budgets'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function managerItems(): array
    {
        return [
            ['route' => 'dashboard', 'pattern' => ['dashboard*'], 'label' => 'Dashboard'],
            ['route' => 'reports.index', 'pattern' => ['reports.index'], 'label' => 'Reports'],
            ['route' => 'requests.index', 'pattern' => ['requests.index'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'budgets.index', 'pattern' => ['budgets.*'], 'label' => 'Budgets'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function staffItems(): array
    {
        return [
            ['route' => 'dashboard', 'pattern' => ['dashboard*'], 'label' => 'Dashboard'],
            ['route' => 'requests.index', 'pattern' => ['requests.index'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function auditorItems(): array
    {
        return [
            ['route' => 'dashboard', 'pattern' => ['dashboard*'], 'label' => 'Dashboard'],
            ['route' => 'requests.index', 'pattern' => ['requests.index'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'vendors.index', 'pattern' => ['vendors.index', 'vendors.show', 'vendors.reports'], 'label' => 'Vendors'],
            ['route' => 'budgets.index', 'pattern' => ['budgets.*'], 'label' => 'Budgets'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function platformItems(): array
    {
        return [
            ['route' => 'platform.dashboard', 'pattern' => ['platform.dashboard'], 'label' => 'Dashboard'],
            ['route' => 'platform.tenants', 'pattern' => ['platform.tenants'], 'label' => 'Tenant / Org Management'],
            ['route' => 'platform.users', 'pattern' => ['platform.users'], 'label' => 'Platform Users'],
        ];
    }

    /**
     * @param  array<int, array{route: string, pattern: array<int, string>, label: string}>  $items
     */
    private function containsRoute(array $items, string $route): bool
    {
        foreach ($items as $item) {
            if (($item['route'] ?? '') === $route) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{route: string, pattern: array<int, string>, label: string}>  $items
     * @param  array{route: string, pattern: array<int, string>, label: string}  $itemToInsert
     */
    private function insertAfter(array &$items, string $targetRoute, array $itemToInsert): void
    {
        foreach ($items as $index => $item) {
            if (($item['route'] ?? '') === $targetRoute) {
                array_splice($items, $index + 1, 0, [$itemToInsert]);

                return;
            }
        }

        $items[] = $itemToInsert;
    }
}
