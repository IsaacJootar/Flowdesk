<?php

namespace App\Actions\Accounting;

use App\Domains\Accounting\Models\AccountingSyncEvent;
use App\Enums\AccountingCategory;
use App\Enums\AccountingProvider;
use App\Enums\AccountingSyncStatus;
use App\Services\Accounting\AccountMappingService;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAccountingSyncEvent
{
    public function __construct(
        private readonly AccountMappingService $accountMappingService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, string $provider = 'csv', ?int $actorUserId = null): AccountingSyncEvent
    {
        $provider = AccountingProvider::normalize($provider);

        $validated = Validator::make($input, [
            'company_id' => ['required', 'integer', 'min:1'],
            'source_type' => ['required', 'string', 'max:60'],
            'source_id' => ['required', 'integer', 'min:1'],
            'event_type' => ['required', 'string', 'max:80'],
            'category_key' => ['nullable', 'string', Rule::in(AccountingCategory::values())],
            'amount' => ['required', 'integer'],
            'currency_code' => ['required', 'string', 'size:3'],
            'event_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $companyId = (int) $validated['company_id'];
        $categoryKey = AccountingCategory::normalize($validated['category_key'] ?? null);
        $debitAccountCode = $this->accountMappingService->accountCodeFor($companyId, $categoryKey, $provider);
        $status = $categoryKey !== null && $debitAccountCode !== null
            ? AccountingSyncStatus::Pending->value
            : AccountingSyncStatus::NeedsMapping->value;
        $lastError = $status === AccountingSyncStatus::NeedsMapping->value
            ? $this->missingMappingMessage($categoryKey)
            : null;

        $event = DB::transaction(function () use ($validated, $provider, $categoryKey, $debitAccountCode, $status, $lastError): AccountingSyncEvent {
            $event = AccountingSyncEvent::query()
                ->withoutGlobalScopes()
                ->firstOrNew([
                    'company_id' => (int) $validated['company_id'],
                    'source_type' => (string) $validated['source_type'],
                    'source_id' => (int) $validated['source_id'],
                    'event_type' => (string) $validated['event_type'],
                    'provider' => $provider,
                ]);

            if ($event->exists && in_array((string) $event->status, [
                AccountingSyncStatus::Exported->value,
                AccountingSyncStatus::Syncing->value,
                AccountingSyncStatus::Synced->value,
            ], true)) {
                return $event;
            }

            $event->fill([
                'provider' => $provider,
                'category_key' => $categoryKey,
                'amount' => (int) $validated['amount'],
                'currency_code' => strtoupper((string) $validated['currency_code']),
                'event_date' => (string) $validated['event_date'],
                'description' => trim((string) $validated['description']),
                'debit_account_code' => $debitAccountCode,
                'credit_account_code' => $event->credit_account_code,
                'status' => $status,
                'last_error' => $lastError,
                'metadata' => (array) ($validated['metadata'] ?? []),
            ])->save();

            return $event;
        });

        if ($event->wasRecentlyCreated) {
            $this->activityLogger->log(
                action: 'accounting.sync_event.queued',
                entityType: AccountingSyncEvent::class,
                entityId: (int) $event->id,
                metadata: [
                    'source_type' => (string) $event->source_type,
                    'source_id' => (int) $event->source_id,
                    'event_type' => (string) $event->event_type,
                    'status' => (string) $event->status,
                    'provider' => (string) $event->provider,
                ],
                companyId: (int) $event->company_id,
                userId: $actorUserId,
            );
        }

        return $event;
    }

    private function missingMappingMessage(?string $categoryKey): string
    {
        if ($categoryKey === null) {
            return 'Choose a Spend Type before this record can be exported or synced.';
        }

        return 'Map '.AccountingCategory::labelFor($categoryKey).' in Chart of Accounts before export or sync.';
    }
}
