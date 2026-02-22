<?php

namespace App\Http\Controllers;

use App\Domains\Expenses\Models\ExpenseAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseAttachmentDownloadController extends Controller
{
    public function __invoke(ExpenseAttachment $attachment): StreamedResponse
    {
        $expense = $attachment->expense;

        abort_if(! $expense, 404);

        Gate::authorize('view', $expense);

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($attachment->file_path)) {
            return $localDisk->download($attachment->file_path, $attachment->original_name);
        }

        // Backward compatibility: legacy attachments were saved on the public disk.
        $publicDisk = Storage::disk('public');
        abort_unless($publicDisk->exists($attachment->file_path), 404);

        return $publicDisk->download($attachment->file_path, $attachment->original_name);
    }
}
