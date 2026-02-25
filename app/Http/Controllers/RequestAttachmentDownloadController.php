<?php

namespace App\Http\Controllers;

use App\Domains\Requests\Models\RequestAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestAttachmentDownloadController extends Controller
{
    public function __invoke(RequestAttachment $attachment): StreamedResponse
    {
        $request = $attachment->request;
        abort_if(! $request, 404);

        Gate::authorize('view', $request);

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($attachment->file_path)) {
            return $localDisk->download($attachment->file_path, $attachment->original_name);
        }

        $publicDisk = Storage::disk('public');
        abort_unless($publicDisk->exists($attachment->file_path), 404);

        return $publicDisk->download($attachment->file_path, $attachment->original_name);
    }
}

