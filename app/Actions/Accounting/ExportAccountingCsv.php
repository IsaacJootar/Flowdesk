<?php

namespace App\Actions\Accounting;

use App\Domains\Accounting\Models\AccountingExportBatch;
use App\Domains\Accounting\Models\AccountingSyncEvent;
use App\Enums\AccountingSyncStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Accounting\AccountingExportCsvWriter;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExportAccountingCsv
{
    public function __construct(
        private readonly AccountingExportCsvWriter $csvWriter,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, array $input): AccountingExportBatch
    {
        if (! in_array((string) $actor->role, [UserRole::Owner->value, UserRole::Finance->value], true)) {
            throw new AuthorizationException('Only owner and finance can export accounting records.');
        }

        if (! $actor->company_id) {
            throw ValidationException::withMessages([
                'company' => 'You must belong to an organization before exporting accounting records.',
            ]);
        }

        $validated = Validator::make($input, [
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ])->validate();

        $companyId = (int) $actor->company_id;
        $from = (string) $validated['from_date'];
        $to = (string) $validated['to_date'];

        $missingCount = AccountingSyncEvent::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('provider', 'csv')
            ->whereDate('event_date', '>=', $from)
            ->whereDate('event_date', '<=', $to)
            ->where('status', AccountingSyncStatus::NeedsMapping->value)
            ->count();

        if ($missingCount > 0) {
            throw ValidationException::withMessages([
                'mapping' => $missingCount.' accounting record(s) need Chart of Accounts mapping before export.',
            ]);
        }

        $events = AccountingSyncEvent::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('provider', 'csv')
            ->whereDate('event_date', '>=', $from)
            ->whereDate('event_date', '<=', $to)
            ->where('status', AccountingSyncStatus::Pending->value)
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            throw ValidationException::withMessages([
                'records' => 'No ready accounting records were found in this date range.',
            ]);
        }

        $batch = DB::transaction(function () use ($actor, $companyId, $from, $to, $events): AccountingExportBatch {
            $batch = AccountingExportBatch::query()->create([
                'company_id' => $companyId,
                'from_date' => $from,
                'to_date' => $to,
                'status' => 'completed',
                'row_count' => $events->count(),
                'warning_count' => 0,
                'file_path' => null,
                'created_by' => (int) $actor->id,
                'metadata' => [
                    'provider' => 'csv',
                    'event_ids' => $events->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                ],
            ]);

            $path = $this->csvWriter->write($companyId, (int) $batch->id, $events);
            $batch->forceFill(['file_path' => $path])->save();

            AccountingSyncEvent::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('id', $events->pluck('id')->all())
                ->update([
                    'status' => AccountingSyncStatus::Exported->value,
                    'export_batch_id' => (int) $batch->id,
                    'updated_at' => now(),
                ]);

            return $batch;
        });

        $this->activityLogger->log(
            action: 'accounting.csv_export.created',
            entityType: AccountingExportBatch::class,
            entityId: (int) $batch->id,
            metadata: [
                'from_date' => $from,
                'to_date' => $to,
                'row_count' => (int) $batch->row_count,
            ],
            companyId: $companyId,
            userId: (int) $actor->id,
        );

        return $batch;
    }
}
