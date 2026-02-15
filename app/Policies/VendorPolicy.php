<?php

namespace App\Policies;

use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Models\User;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->is_active
            && (int) $user->company_id === (int) ($vendor->company_id ?? 0);
    }

    public function create(User $user): bool
    {
        return $user->is_active
            && in_array($user->role, [UserRole::Owner->value, UserRole::Finance->value], true);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->view($user, $vendor) && $this->create($user);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $this->update($user, $vendor);
    }
}
