<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\Vendor;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;

class DeleteVendor
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function __invoke(User $user, Vendor $vendor): void
    {
        Gate::forUser($user)->authorize('delete', $vendor);

        $vendorSnapshot = $vendor->only([
            'id',
            'name',
            'vendor_type',
            'contact_person',
            'phone',
            'email',
            'is_active',
        ]);

        $vendor->delete();

        $this->activityLogger->log(
            action: 'vendor.deleted',
            entityType: Vendor::class,
            entityId: $vendor->id,
            metadata: [
                'vendor' => $vendorSnapshot,
            ],
            companyId: $vendor->company_id,
            userId: $user->id,
        );
    }
}
