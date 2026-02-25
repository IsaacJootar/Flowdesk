<?php

namespace App\Services;

use App\Domains\Vendors\Models\CompanyVendorPolicySetting;
use App\Domains\Vendors\Models\Vendor;
use App\Models\User;

class VendorPolicyResolver
{
    public function canViewDirectory(User $user): bool
    {
        return $this->roleAllowedForAction($user, CompanyVendorPolicySetting::ACTION_VIEW_DIRECTORY);
    }

    public function canCreateVendor(User $user): bool
    {
        return $this->roleAllowedForAction($user, CompanyVendorPolicySetting::ACTION_CREATE_VENDOR);
    }

    public function canUpdateVendor(User $user, Vendor $vendor): bool
    {
        return $this->allowedOnVendor($user, $vendor, CompanyVendorPolicySetting::ACTION_UPDATE_VENDOR);
    }

    public function canDeleteVendor(User $user, Vendor $vendor): bool
    {
        return $this->allowedOnVendor($user, $vendor, CompanyVendorPolicySetting::ACTION_DELETE_VENDOR);
    }

    public function canManageInvoices(User $user, Vendor $vendor): bool
    {
        return $this->allowedOnVendor($user, $vendor, CompanyVendorPolicySetting::ACTION_MANAGE_INVOICES);
    }

    public function canRecordPayments(User $user, Vendor $vendor): bool
    {
        return $this->allowedOnVendor($user, $vendor, CompanyVendorPolicySetting::ACTION_RECORD_PAYMENTS);
    }

    public function canExportStatements(User $user, Vendor $vendor): bool
    {
        return $this->allowedOnVendor($user, $vendor, CompanyVendorPolicySetting::ACTION_EXPORT_STATEMENTS);
    }

    public function canManageCommunications(User $user, Vendor $vendor): bool
    {
        return $this->allowedOnVendor($user, $vendor, CompanyVendorPolicySetting::ACTION_MANAGE_COMMUNICATIONS);
    }

    public function settingsForCompany(int $companyId): CompanyVendorPolicySetting
    {
        return CompanyVendorPolicySetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                array_merge(
                    CompanyVendorPolicySetting::defaultAttributes(),
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

    private function allowedOnVendor(User $user, Vendor $vendor, string $action): bool
    {
        // Vendor controls are always tenant-scoped first, then role/action evaluated.
        if ((int) $user->company_id !== (int) $vendor->company_id) {
            return false;
        }

        return $this->roleAllowedForAction($user, $action);
    }
}

