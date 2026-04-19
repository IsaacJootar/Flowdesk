<?php

namespace App\Http\Controllers;

use App\Domains\Accounting\Models\AccountingExportBatch;
use App\Enums\UserRole;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingExportDownloadController extends Controller
{
    public function __invoke(AccountingExportBatch $batch): BinaryFileResponse|Response
    {
        $user = Auth::user();
        abort_unless($user && (int) $batch->company_id === (int) $user->company_id, 403);
        abort_unless(in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Auditor->value,
        ], true), 403);

        $path = (string) $batch->file_path;
        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        return response()->download(
            Storage::disk('local')->path($path),
            basename($path),
            ['Content-Type' => 'text/csv']
        );
    }
}
