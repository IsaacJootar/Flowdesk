<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordVendorInvoicePayment
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, VendorInvoice $invoice, array $input): VendorInvoicePayment
    {
        Gate::forUser($user)->authorize('recordPayments', $invoice->vendor);

        if ((string) $invoice->status === VendorInvoice::STATUS_VOID) {
            throw ValidationException::withMessages([
                'invoice' => 'Cannot record payment on void invoice.',
            ]);
        }

        if ((int) $invoice->outstanding_amount <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice is already fully paid.',
            ]);
        }

        $validated = Validator::make($input, [
            'amount' => ['required', 'integer', 'min:1'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'pos', 'online', 'cheque'])],
            'payment_reference' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('vendor_invoice_payments', 'payment_reference')
                    ->where(fn ($query) => $query->where('company_id', (int) $invoice->company_id)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $amount = (int) $validated['amount'];
        if ($amount > (int) $invoice->outstanding_amount) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds outstanding invoice amount.',
            ]);
        }

        /** @var VendorInvoicePayment $payment */
        $payment = DB::transaction(function () use ($user, $invoice, $validated, $amount): VendorInvoicePayment {
            $payment = VendorInvoicePayment::query()->create([
                'company_id' => (int) $invoice->company_id,
                'vendor_id' => (int) $invoice->vendor_id,
                'vendor_invoice_id' => (int) $invoice->id,
                'payment_reference' => $this->nullableString($validated['payment_reference'] ?? null),
                'amount' => $amount,
                'payment_date' => (string) $validated['payment_date'],
                'payment_method' => $this->nullableString($validated['payment_method'] ?? null),
                'notes' => $this->nullableString($validated['notes'] ?? null),
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ]);

            $newPaid = (int) $invoice->paid_amount + $amount;
            $newOutstanding = max(0, (int) $invoice->total_amount - $newPaid);
            $invoice->forceFill([
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newOutstanding === 0 ? VendorInvoice::STATUS_PAID : VendorInvoice::STATUS_PART_PAID,
                'updated_by' => (int) $user->id,
            ])->save();

            return $payment;
        });

        $this->activityLogger->log(
            action: 'vendor.invoice.payment.recorded',
            entityType: VendorInvoicePayment::class,
            entityId: $payment->id,
            metadata: [
                'vendor_id' => $payment->vendor_id,
                'invoice_id' => $payment->vendor_invoice_id,
                'amount' => $payment->amount,
                'payment_reference' => $payment->payment_reference,
            ],
            companyId: (int) $payment->company_id,
            userId: (int) $user->id,
        );

        return $payment;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
