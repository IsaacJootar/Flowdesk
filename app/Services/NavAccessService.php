<?php

namespace App\Services;

use App\Domains\Company\Models\Company;
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
     *   items: array<int, array{route: string, pattern: array<int, string>, label: string, icon: string, params?: array<string,mixed>}>,
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
            $items = $this->platformItems();

            return [
                'items' => $this->attachIcons($items),
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
            'items' => $this->attachIcons($items),
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
            ['route' => 'operations.control-desk', 'pattern' => ['operations.control-desk', 'operations.approval-desk', 'operations.vendor-payables-desk', 'operations.period-close-desk'], 'label' => 'Operations Desk'],
            ['route' => 'reports.index', 'pattern' => ['reports.index'], 'label' => 'Reports'],
            ['route' => 'execution.health', 'pattern' => ['execution.health', 'execution.payout-ready', 'execution.help'], 'label' => 'Executions & Payouts'],
            ['route' => 'requests.index', 'pattern' => ['requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications', 'requests.communications-help'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'vendors.index', 'pattern' => ['vendors.index', 'vendors.registry', 'vendors.show', 'vendors.reports'], 'label' => 'Vendor Management'],
            ['route' => 'procurement.release-desk', 'pattern' => ['procurement.*'], 'label' => 'Manage Procurement'],
            ['route' => 'treasury.reconciliation', 'pattern' => ['treasury.*'], 'label' => 'Manage Treasury'],
            ['route' => 'budgets.index', 'pattern' => ['budgets.*'], 'label' => 'Budgets'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
            ['route' => 'organization.admin-desk', 'pattern' => ['organization.admin-desk', 'departments.*', 'team.*', 'approval-workflows.*'], 'label' => 'Organization Admin'],
            ['route' => 'settings.index', 'pattern' => ['settings.*'], 'label' => 'Settings'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string}>
     */
    private function financeItems(): array
    {
        return [
            ['route' => 'dashboard', 'pattern' => ['dashboard*'], 'label' => 'Dashboard'],
            ['route' => 'operations.control-desk', 'pattern' => ['operations.control-desk', 'operations.approval-desk', 'operations.vendor-payables-desk', 'operations.period-close-desk'], 'label' => 'Operations Desk'],
            ['route' => 'reports.index', 'pattern' => ['reports.index'], 'label' => 'Reports'],
            ['route' => 'execution.health', 'pattern' => ['execution.health', 'execution.payout-ready', 'execution.help'], 'label' => 'Execution & Payouts'],
            ['route' => 'requests.index', 'pattern' => ['requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications', 'requests.communications-help'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'vendors.index', 'pattern' => ['vendors.index', 'vendors.registry', 'vendors.show', 'vendors.reports'], 'label' => 'Vendor Management Workspace'],
            ['route' => 'procurement.release-desk', 'pattern' => ['procurement.*'], 'label' => 'Manage Procurement'],
            ['route' => 'treasury.reconciliation', 'pattern' => ['treasury.*'], 'label' => 'Manage Treasury'],
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
            ['route' => 'operations.control-desk', 'pattern' => ['operations.control-desk', 'operations.approval-desk', 'operations.vendor-payables-desk', 'operations.period-close-desk'], 'label' => 'Operations Desk'],
            ['route' => 'reports.index', 'pattern' => ['reports.index'], 'label' => 'Reports'],
            ['route' => 'execution.health', 'pattern' => ['execution.health', 'execution.payout-ready', 'execution.help'], 'label' => 'Execution & Payouts'],
            ['route' => 'requests.index', 'pattern' => ['requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications', 'requests.communications-help'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'procurement.release-desk', 'pattern' => ['procurement.*'], 'label' => 'Manage Procurement'],
            ['route' => 'treasury.reconciliation', 'pattern' => ['treasury.*'], 'label' => 'Manage Treasury'],
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
            ['route' => 'requests.index', 'pattern' => ['requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications', 'requests.communications-help'], 'label' => 'Inbox & Logs'],
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
            ['route' => 'operations.control-desk', 'pattern' => ['operations.control-desk', 'operations.approval-desk', 'operations.vendor-payables-desk', 'operations.period-close-desk'], 'label' => 'Operations Desk'],
            ['route' => 'execution.health', 'pattern' => ['execution.health', 'execution.payout-ready', 'execution.help'], 'label' => 'Execution & Payouts'],
            ['route' => 'requests.index', 'pattern' => ['requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help'], 'label' => 'Requests & Approvals'],
            ['route' => 'requests.communications', 'pattern' => ['requests.communications', 'requests.communications-help'], 'label' => 'Inbox & Logs'],
            ['route' => 'requests.reports', 'pattern' => ['requests.reports'], 'label' => 'Request Reports'],
            ['route' => 'expenses.index', 'pattern' => ['expenses.*'], 'label' => 'Expenses'],
            ['route' => 'vendors.index', 'pattern' => ['vendors.index', 'vendors.registry', 'vendors.show', 'vendors.reports'], 'label' => 'Vendor Management Workspace'],
            ['route' => 'procurement.release-desk', 'pattern' => ['procurement.*'], 'label' => 'Manage Procurement'],
            ['route' => 'treasury.reconciliation', 'pattern' => ['treasury.*'], 'label' => 'Manage Treasury'],
            ['route' => 'budgets.index', 'pattern' => ['budgets.*'], 'label' => 'Budgets'],
            ['route' => 'assets.index', 'pattern' => ['assets.*'], 'label' => 'Assets'],
        ];
    }

    /**
     * @return array<int, array{route: string, pattern: array<int, string>, label: string, params?: array<string,mixed>}>
     */
    private function platformItems(): array
    {
        $items = [
            ['route' => 'platform.dashboard', 'pattern' => ['platform.dashboard'], 'label' => 'Dashboard'],
            ['route' => 'platform.tenants', 'pattern' => ['platform.tenants'], 'label' => 'Tenant / Org Management'],
            ['route' => 'platform.users', 'pattern' => ['platform.users'], 'label' => 'Platform Users'],
            ['route' => 'platform.operations.hub', 'pattern' => ['platform.operations.hub', 'platform.operations.execution', 'platform.operations.execution-checklist', 'platform.operations.incident-history', 'platform.operations.pilot-rollout'], 'label' => 'Operations Hub'],
        ];

        $activeTenantId = $this->currentPlatformTenantId() ?? $this->firstExternalTenantId();

        if ($activeTenantId) {
            $params = ['company' => $activeTenantId];
            $items[] = ['route' => 'platform.tenants.show', 'pattern' => ['platform.tenants.show'], 'label' => 'Tenant Profile', 'params' => $params];
            $items[] = ['route' => 'platform.tenants.plan-entitlements', 'pattern' => ['platform.tenants.plan-entitlements'], 'label' => 'Tenant Plan & Modules', 'params' => $params];
            $items[] = ['route' => 'platform.tenants.billing', 'pattern' => ['platform.tenants.billing'], 'label' => 'Tenant Billing', 'params' => $params];
            $items[] = ['route' => 'platform.tenants.execution-mode', 'pattern' => ['platform.tenants.execution-mode'], 'label' => 'Tenant Execution Mode', 'params' => $params];
            $items[] = ['route' => 'platform.tenants.execution-policy', 'pattern' => ['platform.tenants.execution-policy'], 'label' => 'Tenant Execution Policy', 'params' => $params];
        }

        return $items;
    }

    /**
     * @param  array<int, array{route: string, pattern: array<int, string>, label: string, params?: array<string,mixed>}>  $items
     * @return array<int, array{route: string, pattern: array<int, string>, label: string, icon: string, params?: array<string,mixed>}>
     */
    private function attachIcons(array $items): array
    {
        return array_map(function (array $item): array {
            $item['icon'] = $this->iconForRoute((string) ($item['route'] ?? ''));

            return $item;
        }, $items);
    }

    private function iconForRoute(string $route): string
    {
        return match ($route) {
            'dashboard', 'platform.dashboard' => 'home',
            'reports.index', 'requests.reports' => 'chart',
            'requests.index', 'requests.lifecycle-desk', 'requests.lifecycle-help' => 'clipboard',
            'requests.communications' => 'chat',
            'expenses.index' => 'receipt',
            'procurement.release-desk', 'procurement.release-help', 'procurement.orders', 'procurement.receipts', 'procurement.match-exceptions' => 'clipboard',
            'treasury.reconciliation', 'treasury.reconciliation-exceptions' => 'flow',
            'treasury.payment-runs' => 'clipboard',
            'treasury.cash-position' => 'wallet',
            'vendors.index', 'vendors.registry', 'platform.tenants', 'platform.tenants.show' => 'building',
            'budgets.index', 'platform.tenants.billing' => 'wallet',
            'assets.index' => 'cube',
            'organization.admin-desk', 'departments.index' => 'office',
            'team.index', 'platform.users' => 'users',
            'platform.operations.hub' => 'flow',
            'platform.operations.execution' => 'flow',
            'platform.operations.execution-checklist' => 'clipboard',
            'platform.operations.incident-history' => 'chart',
            'platform.operations.pilot-rollout' => 'chart',
            'execution.health' => 'flow',
            'operations.control-desk' => 'flow',
            'operations.approval-desk' => 'flow',
            'operations.vendor-payables-desk' => 'flow',
            'operations.period-close-desk' => 'flow',
            'approval-workflows.index', 'platform.tenants.execution-mode' => 'flow',
            'settings.communications' => 'chat',
            'settings.request-configuration', 'platform.tenants.plan-entitlements' => 'sliders',
            'settings.approval-timing-controls' => 'clock',
            'settings.expense-controls', 'settings.asset-controls', 'settings.vendor-controls', 'settings.procurement-controls', 'settings.treasury-controls', 'settings.payments-rails', 'platform.tenants.execution-policy' => 'shield',
            'settings.index' => 'cog',
            default => 'dot',
        };
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

    private function currentPlatformTenantId(): ?int
    {
        $routeCompany = request()->route('company');

        if ($routeCompany instanceof Company) {
            return (int) $routeCompany->id;
        }

        if (is_numeric($routeCompany)) {
            return (int) $routeCompany;
        }

        $sessionId = (int) session('platform_active_tenant_id', 0);

        return $sessionId > 0 ? $sessionId : null;
    }

    private function firstExternalTenantId(): ?int
    {
        $internalSlugs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            (array) config('platform.internal_company_slugs', [])
        ))));

        $id = Company::query()
            ->when(
                $internalSlugs !== [],
                fn ($query) => $query->whereNotIn('slug', $internalSlugs)
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
    }
}












