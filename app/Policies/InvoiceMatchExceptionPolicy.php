<?php

namespace App\Policies;

use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Models\User;
use App\Services\Procurement\ProcurementControlSettingsService;

class InvoiceMatchExceptionPolicy
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService
    ) {
    }

    public function viewAny(User $user): bool
    {
        return app(PurchaseOrderPolicy::class)->viewAny($user);
    }

    public function view(User $user, InvoiceMatchException $exception): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) $exception->company_id;
    }

    public function resolve(User $user, InvoiceMatchException $exception): bool
    {
        if (! $this->view($user, $exception)) {
            return false;
        }

        $controls = $this->settingsService->effectiveControls((int) $user->company_id);
        $allowedRoles = array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            (array) ($controls['match_override_allowed_roles'] ?? ['owner', 'finance'])
        )));

        return in_array(strtolower((string) $user->role), $allowedRoles, true);
    }
}

