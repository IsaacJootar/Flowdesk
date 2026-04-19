<?php

namespace App\Actions\Accounting;

use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Enums\AccountingCategory;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveChartOfAccountMappings
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    /**
     * @param  array<string, array{account_code?: mixed, account_name?: mixed}>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, array $input, string $provider = 'csv'): void
    {
        if (! in_array((string) $actor->role, [UserRole::Owner->value, UserRole::Finance->value], true)) {
            throw new AuthorizationException('Only owner and finance can update Chart of Accounts.');
        }

        if (! $actor->company_id) {
            throw ValidationException::withMessages([
                'company' => 'You must belong to an organization before updating Chart of Accounts.',
            ]);
        }

        $provider = $this->normalizeProvider($provider);
        $rules = [
            'mappings' => ['required', 'array'],
            'mappings.*.account_code' => ['nullable', 'string', 'max:50'],
            'mappings.*.account_name' => ['nullable', 'string', 'max:160'],
        ];

        foreach (array_keys($input) as $categoryKey) {
            if (! in_array((string) $categoryKey, AccountingCategory::values(), true)) {
                throw ValidationException::withMessages([
                    'mappings' => 'One of the Spend Types is not supported.',
                ]);
            }
        }

        $validated = Validator::make(['mappings' => $input], $rules)->validate();
        $rows = (array) ($validated['mappings'] ?? []);

        DB::transaction(function () use ($actor, $provider, $rows): void {
            foreach (AccountingCategory::values() as $categoryKey) {
                $row = (array) ($rows[$categoryKey] ?? []);
                $accountCode = $this->nullableString($row['account_code'] ?? null);
                $accountName = $this->nullableString($row['account_name'] ?? null);

                if ($accountCode === null) {
                    ChartOfAccountMapping::query()
                        ->where('company_id', (int) $actor->company_id)
                        ->where('provider', $provider)
                        ->where('category_key', $categoryKey)
                        ->delete();

                    continue;
                }

                $mapping = ChartOfAccountMapping::query()->firstOrNew([
                    'company_id' => (int) $actor->company_id,
                    'provider' => $provider,
                    'category_key' => $categoryKey,
                ]);

                if (! $mapping->exists) {
                    $mapping->created_by = (int) $actor->id;
                }

                $mapping->fill([
                    'account_code' => $accountCode,
                    'account_name' => $accountName,
                    'updated_by' => (int) $actor->id,
                ])->save();
            }
        });

        $readyCount = ChartOfAccountMapping::query()
            ->where('company_id', (int) $actor->company_id)
            ->where('provider', $provider)
            ->whereIn('category_key', AccountingCategory::values())
            ->count();

        $this->activityLogger->log(
            action: 'accounting.chart_of_accounts.updated',
            entityType: ChartOfAccountMapping::class,
            metadata: [
                'provider' => $provider,
                'ready_count' => (int) $readyCount,
                'total_count' => count(AccountingCategory::values()),
            ],
            companyId: (int) $actor->company_id,
            userId: (int) $actor->id,
        );
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return in_array($provider, ['csv', 'quickbooks', 'sage', 'xero'], true) ? $provider : 'csv';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
