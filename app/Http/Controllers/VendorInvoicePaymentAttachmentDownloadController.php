<?php

namespace App\Http\Controllers;

use App\Domains\Vendors\Models\VendorInvoicePaymentAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorInvoicePaymentAttachmentDownloadController extends Controller
{
    public function __invoke(VendorInvoicePaymentAttachment $attachment): StreamedResponse
    {
        $payment = $attachment->payment;
        abort_if(! $payment, 404);

        Gate::authorize('view', $payment->vendor);

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($attachment->file_path)) {
            return $localDisk->download($attachment->file_path, $attachment->original_name);
        }

        $publicDisk = Storage::disk('public');
        abort_unless($publicDisk->exists($attachment->file_path), 404);

        return $publicDisk->download($attachment->file_path, $attachment->original_name);
    }
}

