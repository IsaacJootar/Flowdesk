<?php

namespace App\Services;

use App\Enums\PlatformUserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class PlatformAccessService
{
    public function isPlatformOperator(?User $user): bool
    {
        // Platform operators are explicitly assigned global roles.
        return (bool) (
            $user
            && in_array((string) $user->platform_role, PlatformUserRole::values(), true)
        );
    }

    public function roleLabel(?User $user): ?string
    {
        $role = (string) ($user?->platform_role ?? '');
        if ($role === '') {
            return null;
        }

        return match ($role) {
            PlatformUserRole::PlatformOwner->value => PlatformUserRole::PlatformOwner->label(),
            PlatformUserRole::PlatformBillingAdmin->value => PlatformUserRole::PlatformBillingAdmin->label(),
            PlatformUserRole::PlatformOpsAdmin->value => PlatformUserRole::PlatformOpsAdmin->label(),
            default => 'Platform Admin',
        };
    }

    public function isPlatformOwner(?User $user): bool
    {
        return (bool) ($user && $user->platform_role === PlatformUserRole::PlatformOwner->value);
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizePlatformOperator(): void
    {
        if (! $this->isPlatformOperator(Auth::user())) {
            throw new AuthorizationException('Only platform admin can manage tenants.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizePlatformOwner(): void
    {
        if (! $this->isPlatformOwner(Auth::user())) {
            throw new AuthorizationException('Only platform owner can manage platform users.');
        }
    }
}
