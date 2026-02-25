<?php

namespace App\Actions\Vendors;

use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoiceAttachment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UploadVendorInvoiceAttachment
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, VendorInvoice $invoice, UploadedFile $file): VendorInvoiceAttachment
    {
        Gate::forUser($user)->authorize('manageInvoices', $invoice->vendor);

        Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp']]
        )->validate();

        $path = $file->store("private/vendor-invoice-attachments/{$invoice->company_id}/{$invoice->id}", 'local');

        $attachment = VendorInvoiceAttachment::query()->create([
            'company_id' => (int) $invoice->company_id,
            'vendor_id' => (int) $invoice->vendor_id,
            'vendor_invoice_id' => (int) $invoice->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'uploaded_by' => (int) $user->id,
            'uploaded_at' => now(),
        ]);

        $this->activityLogger->log(
            action: 'vendor.invoice.attachment.uploaded',
            entityType: VendorInvoice::class,
            entityId: (int) $invoice->id,
            metadata: [
                'attachment_id' => (int) $attachment->id,
                'original_name' => (string) $attachment->original_name,
                'file_size' => (int) $attachment->file_size,
            ],
            companyId: (int) $invoice->company_id,
            userId: (int) $user->id,
        );

        return $attachment;
    }
}
