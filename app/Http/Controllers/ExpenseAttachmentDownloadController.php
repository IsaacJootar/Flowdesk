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

        $disk = Storage::disk('public');

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->original_name);
    }
}
