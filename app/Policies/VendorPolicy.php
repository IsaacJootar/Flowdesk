<?php

namespace App\Policies;

use App\Domains\Vendors\Models\Vendor;
use App\Models\User;
use App\Services\VendorPolicyResolver;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return app(VendorPolicyResolver::class)->canViewDirectory($user);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->viewAny($user)
            && (int) $user->company_id === (int) ($vendor->company_id ?? 0);
    }

    public function create(User $user): bool
    {
        return app(VendorPolicyResolver::class)->canCreateVendor($user);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return app(VendorPolicyResolver::class)->canUpdateVendor($user, $vendor);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return app(VendorPolicyResolver::class)->canDeleteVendor($user, $vendor);
    }

    public function manageInvoices(User $user, Vendor $vendor): bool
    {
        return app(VendorPolicyResolver::class)->canManageInvoices($user, $vendor);
    }

    public function recordPayments(User $user, Vendor $vendor): bool
    {
        return app(VendorPolicyResolver::class)->canRecordPayments($user, $vendor);
    }

    public function exportStatements(User $user, Vendor $vendor): bool
    {
        return app(VendorPolicyResolver::class)->canExportStatements($user, $vendor);
    }

    public function manageCommunications(User $user, Vendor $vendor): bool
    {
        return app(VendorPolicyResolver::class)->canManageCommunications($user, $vendor);
    }
}

