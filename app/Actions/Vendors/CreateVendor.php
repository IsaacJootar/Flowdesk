<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\Vendor;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateVendor
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): Vendor
    {
        Gate::forUser($user)->authorize('create', Vendor::class);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'User must belong to a company before creating vendors.',
            ]);
        }

        $validated = Validator::make($input, $this->rules())->validate();

        $vendor = Vendor::create([
            'company_id' => $user->company_id,
            'name' => $validated['name'],
            'vendor_type' => $validated['vendor_type'],
            'contact_person' => $validated['contact_person'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'bank_name' => $validated['bank_name'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'notes' => $validated['notes'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        $this->activityLogger->log(
            action: 'vendor.created',
            entityType: Vendor::class,
            entityId: $vendor->id,
            metadata: [
                'name' => $vendor->name,
                'vendor_type' => $vendor->vendor_type,
                'is_active' => $vendor->is_active,
            ],
            companyId: $user->company_id,
            userId: $user->id,
        );

        return $vendor;
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'vendor_type' => ['required', Rule::in(['supplier', 'contractor', 'service', 'other'])],
            'contact_person' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'bank_name' => ['required', 'string', 'max:180'],
            'account_name' => ['required', 'string', 'max:180'],
            'account_number' => ['required', 'string', 'max:80'],
            'notes' => ['required', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
