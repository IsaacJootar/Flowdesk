<?php

namespace App\Services\Treasury;

use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportBankStatementCsvService
{
    public function __construct(
        private readonly TreasuryControlSettingsService $treasuryControlSettingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    /**
     * @return array{statement:BankStatement,imported:int,skipped:int}
     *
     * @throws ValidationException
     */
    public function import(User $actor, int $bankAccountId, UploadedFile $csv): array
    {
        $account = BankAccount::query()->whereKey($bankAccountId)->firstOrFail();
        if ((int) $account->company_id !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'account' => 'Bank account is outside your tenant scope.',
            ]);
        }

        $controls = $this->treasuryControlSettingsService->effectiveControls((int) $actor->company_id);
        $maxRows = (int) ($controls['statement_import_max_rows'] ?? 5000);

        $handle = fopen($csv->getRealPath(), 'rb');
        if (! is_resource($handle)) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read statement file.',
            ]);
        }

        $header = fgetcsv($handle) ?: [];
        $normalizedHeader = array_map(static fn ($value) => strtolower(trim((string) $value)), $header);

        $requiredColumns = ['posted_at', 'direction', 'amount'];
        foreach ($requiredColumns as $column) {
            if (! in_array($column, $normalizedHeader, true)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => sprintf('CSV is missing required column: %s', $column),
                ]);
            }
        }

        $columnIndex = array_flip($normalizedHeader);
        $rows = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if ($rowCount > $maxRows) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => sprintf('Statement exceeds max rows (%d).', $maxRows),
                ]);
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rows[] = $this->normalizeRow($row, $columnIndex);
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'Statement file has no usable rows.',
            ]);
        }

        $statementReference = sprintf('STMT-%d-%s', (int) $account->id, now()->format('YmdHis').Str::upper(Str::random(4)));

        /** @var array{statement:BankStatement,imported:int,skipped:int,last_posted_at:?Carbon} $result */
        $result = DB::transaction(function () use ($actor, $account, $rows, $statementReference, $csv): array {
            $imported = 0;
            $skipped = 0;
            $lastPostedAt = null;

            $statement = BankStatement::query()->create([
                'company_id' => (int) $account->company_id,
                'bank_account_id' => (int) $account->id,
                'statement_reference' => $statementReference,
                'statement_date' => now()->toDateString(),
                'period_start' => collect($rows)->min('value_date'),
                'period_end' => collect($rows)->max('value_date'),
                'import_status' => BankStatement::STATUS_IMPORTED,
                'imported_at' => now(),
                'imported_by_user_id' => (int) $actor->id,
                'metadata' => [
                    'filename' => (string) $csv->getClientOriginalName(),
                    'rows_received' => count($rows),
                ],
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]);

            foreach ($rows as $row) {
                $line = BankStatementLine::query()->firstOrCreate(
                    [
                        'bank_account_id' => (int) $account->id,
                        'source_hash' => (string) $row['source_hash'],
                    ],
                    [
                        'company_id' => (int) $account->company_id,
                        'bank_statement_id' => (int) $statement->id,
                        'line_reference' => (string) $row['line_reference'],
                        'posted_at' => $row['posted_at'],
                        'value_date' => $row['value_date'],
                        'description' => (string) $row['description'],
                        'direction' => (string) $row['direction'],
                        'amount' => (int) $row['amount'],
                        'currency_code' => (string) $row['currency_code'],
                        'balance_after' => $row['balance_after'],
                        'is_reconciled' => false,
                        'metadata' => [
                            'import_source' => 'csv',
                        ],
                        'created_by' => (int) $actor->id,
                        'updated_by' => (int) $actor->id,
                    ]
                );

                if ($line->wasRecentlyCreated) {
                    $imported++;
                    $lastPostedAt = $lastPostedAt
                        ? max($lastPostedAt, Carbon::parse($row['posted_at']))
                        : Carbon::parse($row['posted_at']);
                } else {
                    $skipped++;
                }
            }

            $statement->forceFill([
                'import_status' => $skipped > 0 ? BankStatement::STATUS_PARTIAL : BankStatement::STATUS_IMPORTED,
                'metadata' => array_merge((array) ($statement->metadata ?? []), [
                    'rows_imported' => $imported,
                    'rows_skipped' => $skipped,
                ]),
            ])->save();

            return [
                'statement' => $statement,
                'imported' => $imported,
                'skipped' => $skipped,
                'last_posted_at' => $lastPostedAt,
            ];
        });

        if ($result['last_posted_at'] instanceof Carbon) {
            $account->forceFill([
                'last_statement_at' => $result['last_posted_at'],
                'updated_by' => (int) $actor->id,
            ])->save();
        }

        $this->tenantAuditLogger->log(
            companyId: (int) $actor->company_id,
            action: 'tenant.treasury.statement.imported',
            actor: $actor,
            description: 'Bank statement imported from treasury reconciliation page.',
            entityType: BankStatement::class,
            entityId: (int) $result['statement']->id,
            metadata: [
                'bank_account_id' => (int) $account->id,
                'statement_reference' => (string) $result['statement']->statement_reference,
                'rows_imported' => (int) $result['imported'],
                'rows_skipped' => (int) $result['skipped'],
            ],
        );

        return [
            'statement' => $result['statement'],
            'imported' => (int) $result['imported'],
            'skipped' => (int) $result['skipped'],
        ];
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columnIndex
     * @return array{line_reference:string,posted_at:string,value_date:?string,description:string,direction:string,amount:int,currency_code:string,balance_after:?int,source_hash:string}
     *
     * @throws ValidationException
     */
    private function normalizeRow(array $row, array $columnIndex): array
    {
        $postedAt = trim((string) ($row[$columnIndex['posted_at']] ?? ''));
        $direction = strtolower(trim((string) ($row[$columnIndex['direction']] ?? '')));
        $amountRaw = trim((string) ($row[$columnIndex['amount']] ?? ''));

        if ($postedAt === '' || ! in_array($direction, ['debit', 'credit'], true) || $amountRaw === '') {
            throw ValidationException::withMessages([
                'file' => 'Statement row has invalid posted_at, direction, or amount.',
            ]);
        }

        // Treasury statement imports currently expect whole-currency values exactly as supplied by the bank export.
        $amount = (int) round((float) str_replace(',', '', $amountRaw));
        $lineReference = trim((string) ($row[$columnIndex['line_reference'] ?? -1] ?? ''));
        $description = trim((string) ($row[$columnIndex['description'] ?? -1] ?? ''));
        $currency = strtoupper(trim((string) ($row[$columnIndex['currency_code'] ?? -1] ?? 'NGN')));
        $valueDate = trim((string) ($row[$columnIndex['value_date'] ?? -1] ?? ''));
        $balanceRaw = trim((string) ($row[$columnIndex['balance_after'] ?? -1] ?? ''));

        $postedAtCarbon = Carbon::parse($postedAt);
        $valueDateString = $valueDate !== '' ? Carbon::parse($valueDate)->toDateString() : $postedAtCarbon->toDateString();
        $balanceAfter = $balanceRaw !== '' ? (int) round((float) str_replace(',', '', $balanceRaw)) : null;

        $sourceHash = hash('sha256', implode('|', [
            $postedAtCarbon->toDateTimeString(),
            $valueDateString,
            $direction,
            (string) $amount,
            strtolower($lineReference),
            strtolower($description),
        ]));

        return [
            'line_reference' => $lineReference,
            'posted_at' => $postedAtCarbon->toDateTimeString(),
            'value_date' => $valueDateString,
            'description' => $description,
            'direction' => $direction,
            'amount' => abs($amount),
            'currency_code' => $currency !== '' ? $currency : 'NGN',
            'balance_after' => $balanceAfter,
            'source_hash' => $sourceHash,
        ];
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
