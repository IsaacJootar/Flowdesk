<?php

namespace App\Policies;

use App\Domains\Assets\Models\Asset;
use App\Services\AssetPolicyResolver;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return app(AssetPolicyResolver::class)->canViewRegistry($user);
    }

    public function view(User $user, Asset $asset): bool
    {
        return (int) $user->company_id === (int) ($asset->company_id ?? 0)
            && app(AssetPolicyResolver::class)->canViewRegistry($user);
    }

    public function create(User $user): bool
    {
        return app(AssetPolicyResolver::class)->canRegisterAsset($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return app(AssetPolicyResolver::class)->canEditAsset($user, $asset);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return app(AssetPolicyResolver::class)->canDisposeAsset($user, $asset);
    }

    public function assign(User $user, Asset $asset): bool
    {
        return app(AssetPolicyResolver::class)->canAssignTransferReturn($user, $asset);
    }

    public function logMaintenance(User $user, Asset $asset): bool
    {
        return app(AssetPolicyResolver::class)->canLogMaintenance($user, $asset);
    }

    public function dispose(User $user, Asset $asset): bool
    {
        return app(AssetPolicyResolver::class)->canDisposeAsset($user, $asset);
    }
}
