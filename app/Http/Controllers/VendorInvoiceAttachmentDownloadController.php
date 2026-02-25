<?php

namespace App\Http\Controllers;

use App\Domains\Vendors\Models\VendorInvoiceAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorInvoiceAttachmentDownloadController extends Controller
{
    public function __invoke(VendorInvoiceAttachment $attachment): StreamedResponse
    {
        $invoice = $attachment->invoice;
        abort_if(! $invoice, 404);

        Gate::authorize('view', $invoice->vendor);

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($attachment->file_path)) {
            return $localDisk->download($attachment->file_path, $attachment->original_name);
        }

        $publicDisk = Storage::disk('public');
        abort_unless($publicDisk->exists($attachment->file_path), 404);

        return $publicDisk->download($attachment->file_path, $attachment->original_name);
    }
}

