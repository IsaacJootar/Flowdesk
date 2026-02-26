<?php

namespace App\Services;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\CompanyAssetPolicySetting;
use App\Models\User;

class AssetPolicyResolver
{
    public function canViewRegistry(User $user): bool
    {
        return $this->roleAllowedForAction($user, CompanyAssetPolicySetting::ACTION_VIEW_REGISTRY);
    }

    public function canRegisterAsset(User $user): bool
    {
        return $this->roleAllowedForAction($user, CompanyAssetPolicySetting::ACTION_REGISTER_ASSET);
    }

    public function canEditAsset(User $user, Asset $asset): bool
    {
        return $this->allowedOnAsset($user, $asset, CompanyAssetPolicySetting::ACTION_EDIT_ASSET);
    }

    public function canAssignTransferReturn(User $user, Asset $asset): bool
    {
        return $this->allowedOnAsset($user, $asset, CompanyAssetPolicySetting::ACTION_ASSIGN_TRANSFER_RETURN);
    }

    public function canLogMaintenance(User $user, Asset $asset): bool
    {
        return $this->allowedOnAsset($user, $asset, CompanyAssetPolicySetting::ACTION_LOG_MAINTENANCE);
    }

    public function canDisposeAsset(User $user, Asset $asset): bool
    {
        return $this->allowedOnAsset($user, $asset, CompanyAssetPolicySetting::ACTION_DISPOSE_ASSET);
    }

    public function settingsForCompany(int $companyId): CompanyAssetPolicySetting
    {
        return CompanyAssetPolicySetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                array_merge(
                    CompanyAssetPolicySetting::defaultAttributes(),
                    ['created_by' => \Illuminate\Support\Facades\Auth::id()]
                )
            );
    }

    private function roleAllowedForAction(User $user, string $action): bool
    {
        if (! $user->is_active || ! $user->company_id) {
            return false;
        }

        $policy = $this->settingsForCompany((int) $user->company_id)->policyForAction($action);

        return in_array((string) $user->role, (array) ($policy['allowed_roles'] ?? []), true);
    }

    private function allowedOnAsset(User $user, Asset $asset, string $action): bool
    {
        // Asset controls are tenant-scoped first, then role/action evaluated.
        if ((int) $user->company_id !== (int) $asset->company_id) {
            return false;
        }

        return $this->roleAllowedForAction($user, $action);
    }
}

