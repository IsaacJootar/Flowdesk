<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VoidVendorInvoice
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, VendorInvoice $invoice, array $input): VendorInvoice
    {
        Gate::forUser($user)->authorize('manageInvoices', $invoice->vendor);

        if ((string) $invoice->status === VendorInvoice::STATUS_VOID) {
            throw ValidationException::withMessages([
                'status' => 'Invoice is already void.',
            ]);
        }

        $validated = Validator::make($input, [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ])->validate();

        $reason = trim((string) $validated['reason']);

        $invoice->forceFill([
            'status' => VendorInvoice::STATUS_VOID,
            'outstanding_amount' => 0,
            'voided_by' => (int) $user->id,
            'voided_at' => now(),
            'void_reason' => $reason,
            'updated_by' => (int) $user->id,
        ])->save();

        $this->activityLogger->log(
            action: 'vendor.invoice.voided',
            entityType: VendorInvoice::class,
            entityId: $invoice->id,
            metadata: [
                'vendor_id' => $invoice->vendor_id,
                'invoice_number' => $invoice->invoice_number,
                'reason' => $reason,
            ],
            companyId: (int) $invoice->company_id,
            userId: (int) $user->id,
        );

        return $invoice;
    }
}
