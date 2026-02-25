<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateVendorInvoice
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Vendor $vendor, array $input): VendorInvoice
    {
        Gate::forUser($user)->authorize('manageInvoices', $vendor);

        $validated = Validator::make($input, [
            'invoice_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('vendor_invoices', 'invoice_number')
                    ->where(fn ($query) => $query->where('company_id', (int) $vendor->company_id)),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $invoice = VendorInvoice::query()->create([
            'company_id' => (int) $vendor->company_id,
            'vendor_id' => (int) $vendor->id,
            'invoice_number' => trim((string) $validated['invoice_number']),
            'invoice_date' => (string) $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'currency' => strtoupper((string) ($user->company?->currency_code ?: 'NGN')),
            'total_amount' => (int) $validated['total_amount'],
            'paid_amount' => 0,
            'outstanding_amount' => (int) $validated['total_amount'],
            'status' => VendorInvoice::STATUS_UNPAID,
            'description' => $this->nullableString($validated['description'] ?? null),
            'notes' => $this->nullableString($validated['notes'] ?? null),
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        $this->activityLogger->log(
            action: 'vendor.invoice.created',
            entityType: VendorInvoice::class,
            entityId: $invoice->id,
            metadata: [
                'vendor_id' => $vendor->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
            ],
            companyId: (int) $vendor->company_id,
            userId: (int) $user->id,
        );

        return $invoice;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
