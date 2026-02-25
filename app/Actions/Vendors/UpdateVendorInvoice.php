<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateVendorInvoice
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
                'status' => 'Void invoice cannot be updated.',
            ]);
        }

        $validated = Validator::make($input, [
            'invoice_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('vendor_invoices', 'invoice_number')
                    ->where(fn ($query) => $query->where('company_id', (int) $invoice->company_id))
                    ->ignore((int) $invoice->id),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $paidAmount = (int) $invoice->paid_amount;
        $newTotal = (int) $validated['total_amount'];
        if ($newTotal < $paidAmount) {
            throw ValidationException::withMessages([
                'total_amount' => 'Total amount cannot be less than already recorded payments.',
            ]);
        }

        $before = $invoice->only([
            'invoice_number',
            'invoice_date',
            'due_date',
            'total_amount',
            'paid_amount',
            'outstanding_amount',
            'status',
            'description',
            'notes',
        ]);

        $invoice->forceFill([
            'invoice_number' => trim((string) $validated['invoice_number']),
            'invoice_date' => (string) $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'total_amount' => $newTotal,
            'outstanding_amount' => max(0, $newTotal - $paidAmount),
            'status' => $this->statusFromAmounts(total: $newTotal, paid: $paidAmount),
            'description' => $this->nullableString($validated['description'] ?? null),
            'notes' => $this->nullableString($validated['notes'] ?? null),
            'updated_by' => (int) $user->id,
        ])->save();

        $this->activityLogger->log(
            action: 'vendor.invoice.updated',
            entityType: VendorInvoice::class,
            entityId: $invoice->id,
            metadata: [
                'vendor_id' => $invoice->vendor_id,
                'before' => $before,
                'after' => $invoice->only(array_keys($before)),
            ],
            companyId: (int) $invoice->company_id,
            userId: (int) $user->id,
        );

        return $invoice;
    }

    private function statusFromAmounts(int $total, int $paid): string
    {
        if ($paid <= 0) {
            return VendorInvoice::STATUS_UNPAID;
        }

        if ($paid >= $total) {
            return VendorInvoice::STATUS_PAID;
        }

        return VendorInvoice::STATUS_PART_PAID;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
