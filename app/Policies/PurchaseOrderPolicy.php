<?php

namespace App\Policies;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Procurement\ProcurementControlSettingsService;

class PurchaseOrderPolicy
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService
    ) {
    }

    public function viewAny(User $user): bool
    {
        if (! $user->is_active || ! $user->company_id) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $order->company_id;
    }

    public function issue(User $user, PurchaseOrder $order): bool
    {
        return $this->view($user, $order)
            && $this->roleAllowedByControl($user, 'issue_allowed_roles', ['owner', 'finance']);
    }

    public function recordReceipt(User $user, PurchaseOrder $order): bool
    {
        return $this->view($user, $order)
            && $this->roleAllowedByControl($user, 'receipt_allowed_roles', ['owner', 'finance', 'manager']);
    }

    public function linkInvoice(User $user, PurchaseOrder $order): bool
    {
        return $this->view($user, $order)
            && $this->roleAllowedByControl($user, 'invoice_link_allowed_roles', ['owner', 'finance']);
    }

    /**
     * @param  array<int, string>  $fallback
     */
    private function roleAllowedByControl(User $user, string $controlKey, array $fallback): bool
    {
        $controls = $this->settingsService->effectiveControls((int) $user->company_id);
        $allowedRoles = array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            (array) ($controls[$controlKey] ?? $fallback)
        )));

        return in_array(strtolower((string) $user->role), $allowedRoles, true);
    }
}

