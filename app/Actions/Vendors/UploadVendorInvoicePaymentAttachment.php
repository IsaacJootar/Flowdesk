<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Domains\Vendors\Models\VendorInvoicePaymentAttachment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UploadVendorInvoicePaymentAttachment
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, VendorInvoicePayment $payment, UploadedFile $file): VendorInvoicePaymentAttachment
    {
        Gate::forUser($user)->authorize('recordPayments', $payment->vendor);

        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp']]
        )->validate();

        $path = $file->store("private/vendor-payment-attachments/{$payment->company_id}/{$payment->id}", 'local');

        $attachment = VendorInvoicePaymentAttachment::query()->create([
            'company_id' => (int) $payment->company_id,
            'vendor_id' => (int) $payment->vendor_id,
            'vendor_invoice_id' => (int) $payment->vendor_invoice_id,
            'vendor_invoice_payment_id' => (int) $payment->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'uploaded_by' => (int) $user->id,
            'uploaded_at' => now(),
        ]);

        $this->activityLogger->log(
            action: 'vendor.invoice.payment.attachment.uploaded',
            entityType: VendorInvoicePayment::class,
            entityId: (int) $payment->id,
            metadata: [
                'attachment_id' => (int) $attachment->id,
                'invoice_id' => (int) $payment->vendor_invoice_id,
                'original_name' => (string) $attachment->original_name,
                'file_size' => (int) $attachment->file_size,
            ],
            companyId: (int) $payment->company_id,
            userId: (int) $user->id,
        );

        return $attachment;
    }
}
