<?php

namespace App\Policies;

use App\Domains\Assets\Models\Asset;
use App\Enums\UserRole;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Asset $asset): bool
    {
        return (int) $user->company_id === (int) ($asset->company_id ?? 0);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Manager->value], true);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset) && $this->create($user);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
