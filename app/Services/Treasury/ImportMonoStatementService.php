<?php

namespace App\Services\Treasury;

use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\MonoConnectAccount;
use App\Models\User;
use App\Services\Mono\MonoConnectService;
use App\Services\TenantAuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imports bank statement lines by pulling transactions directly from Mono Connect.
 *
 * This replaces the manual CSV import step for tenants who have linked their
 * bank account via Mono Connect.  It creates the same BankStatement and
 * BankStatementLine records that ImportBankStatementCsvService produces —
 * so AutoReconcileStatementService works unchanged after import.
 *
 * Balance data is also refreshed on the MonoConnectAccount record so that
 * the Treasury Cash Position dashboard can show live bank balances.
 *
 * Usage flow:
 *   1. Tenant links bank via Mono Connect widget (exchangeAuthCode is called).
 *   2. MonoConnectAccount record exists with a valid mono_account_id.
 *   3. Finance team (or scheduled command) triggers sync for a date range.
 *   4. This service fetches, normalizes, and persists transaction lines.
 *   5. Existing auto-reconciliation runs against the new lines.
 *
 * Mono transaction amounts are in KOBO.  We store them as integers (kobo)
 * in bank_statement_lines.amount — consistent with the CSV import path.
 */
class ImportMonoStatementService
{
    public function __construct(
        private readonly MonoConnectService $monoConnectService,
        private readonly TreasuryControlSettingsService $treasuryControlSettingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {}

    /**
     * Sync a date range of transactions from Mono Connect into the treasury.
     *
     * @param  User        $actor           The authenticated finance user triggering the sync
     * @param  int         $bankAccountId   The BankAccount record to write lines against
     * @param  Carbon      $from            Start of range (inclusive)
     * @param  Carbon      $to              End of range (inclusive)
     * @return array{statement:BankStatement,imported:int,skipped:int,balance_kobo:int|null}
     *
     * @throws ValidationException
     */
    public function sync(User $actor, int $bankAccountId, Carbon $from, Carbon $to): array
    {
        $bankAccount = BankAccount::query()->whereKey($bankAccountId)->firstOrFail();

        if ((int) $bankAccount->company_id !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'account' => 'Bank account is outside your tenant scope.',
            ]);
        }

        // Find the MonoConnectAccount that is linked to this BankAccount
        $monoAccount = MonoConnectAccount::query()
            ->where('bank_account_id', $bankAccountId)
            ->where('is_active', true)
            ->first();

        if ($monoAccount === null) {
            throw ValidationException::withMessages([
                'account' => 'No active Mono Connect account is linked to this bank account. Link via Settings → Treasury first.',
            ]);
        }

        $controls = $this->treasuryControlSettingsService->effectiveControls((int) $actor->company_id);
        $maxRows  = (int) ($controls['statement_import_max_rows'] ?? 5000);

        // Fetch transactions from Mono Connect API
        $result = $this->monoConnectService->fetchTransactions(
            monoAccountId: $monoAccount->mono_account_id,
            from: $from,
            to: $to,
            limit: min($maxRows, 100),
        );

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'sync' => $result['message'] ?? 'Failed to fetch transactions from Mono Connect.',
            ]);
        }

        $transactions = (array) ($result['transactions'] ?? []);

        // Refresh the balance on the MonoConnectAccount using the latest account info
        $accountInfo = $this->monoConnectService->fetchAccountInfo($monoAccount->mono_account_id);
        $balanceKobo = null;
        if ($accountInfo['ok']) {
            $balanceKobo = (int) ($accountInfo['balance_kobo'] ?? 0);
            $monoAccount->update([
                'balance_amount'    => $balanceKobo,
                'balance_synced_at' => now(),
                'last_synced_at'    => now(),
                'sync_error'        => null,
            ]);
        }

        $imported = 0;
        $skipped  = 0;

        DB::transaction(function () use (
            $actor,
            $bankAccount,
            $transactions,
            $from,
            $to,
            $maxRows,
            &$imported,
            &$skipped,
            &$statement
        ): void {
            // Create a BankStatement record to group these lines — same shape as CSV import
            $statement = BankStatement::create([
                'company_id'       => $bankAccount->company_id,
                'bank_account_id'  => $bankAccount->id,
                'statement_reference' => 'MONO-' . strtoupper(substr(sha1($bankAccount->id . $from->toDateString() . $to->toDateString()), 0, 12)),
                'statement_date'   => $to->toDateString(),
                'period_start'     => $from->toDateString(),
                'period_end'       => $to->toDateString(),
                'opening_balance'  => null,
                'closing_balance'  => null,
                'import_status'    => BankStatement::STATUS_IMPORTED,
                'imported_at'      => now(),
                'imported_by_user_id' => $actor->id,
                'metadata'         => [
                    'source'          => 'mono_connect',
                    'mono_account_id' => (string) $bankAccount->account_reference,
                ],
                'created_by'       => $actor->id,
                'updated_by'       => $actor->id,
            ]);

            foreach (array_slice($transactions, 0, $maxRows) as $txn) {
                if (! is_array($txn)) {
                    continue;
                }

                $normalized = $this->normalizeLine($txn, $bankAccount, $statement, $actor);
                if ($normalized === null) {
                    $skipped++;
                    continue;
                }

                // Use source_hash for idempotency — prevents duplicate lines if the same
                // date range is synced twice (matches CSV importer pattern)
                $exists = BankStatementLine::query()
                    ->where('company_id', $bankAccount->company_id)
                    ->where('bank_account_id', $bankAccount->id)
                    ->where('source_hash', $normalized['source_hash'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                BankStatementLine::create($normalized);
                $imported++;
            }

            // Update the statement with final counts
            $statement->update([
                'import_status' => $imported > 0 ? BankStatement::STATUS_IMPORTED : BankStatement::STATUS_PARTIAL,
                'metadata'      => array_merge((array) $statement->metadata, [
                    'total_fetched' => count($transactions),
                    'imported'      => $imported,
                    'skipped'       => $skipped,
                ]),
            ]);

            // Update last_statement_at on the bank account
            $bankAccount->update(['last_statement_at' => now()]);
        });

        $this->tenantAuditLogger->log(
            user: $actor,
            action: 'treasury.mono_statement_synced',
            subject: $statement,
            metadata: [
                'bank_account_id' => $bankAccountId,
                'period_start'    => $from->toDateString(),
                'period_end'      => $to->toDateString(),
                'imported'        => $imported,
                'skipped'         => $skipped,
            ]
        );

        return [
            'statement'    => $statement,
            'imported'     => $imported,
            'skipped'      => $skipped,
            'balance_kobo' => $balanceKobo,
        ];
    }

    /**
     * Normalize a single Mono transaction object into a BankStatementLine insert array.
     * Returns null if the transaction cannot be meaningfully parsed.
     *
     * @param  array<string,mixed>  $txn
     * @return array<string,mixed>|null
     */
    private function normalizeLine(array $txn, BankAccount $bankAccount, BankStatement $statement, User $actor): ?array
    {
        // Mono transaction fields: _id, amount (kobo), type (debit|credit), date, narration, balance
        $rawAmount = $txn['amount'] ?? null;
        if ($rawAmount === null) {
            return null;
        }

        // Amount is stored as integer kobo — negative Mono amounts indicate debits
        $amountKobo = (int) abs((int) $rawAmount);
        if ($amountKobo === 0) {
            return null;
        }

        // Mono uses 'debit' / 'credit' in the `type` field
        $rawType  = strtolower(trim((string) ($txn['type'] ?? '')));
        // Fallback: negative amounts from Mono typically indicate debit
        if ($rawType === '' && is_numeric($txn['amount'] ?? null)) {
            $rawType = (float) $txn['amount'] < 0 ? 'debit' : 'credit';
        }
        $direction = $rawType === 'debit' ? BankStatementLine::DIRECTION_DEBIT : BankStatementLine::DIRECTION_CREDIT;

        // Parse date — Mono returns ISO 8601
        $rawDate = trim((string) ($txn['date'] ?? $txn['created_at'] ?? $txn['updatedAt'] ?? ''));
        try {
            $postedAt = Carbon::parse($rawDate);
        } catch (\Throwable) {
            return null;
        }

        $description   = trim((string) ($txn['narration'] ?? $txn['description'] ?? ''));
        $lineReference = trim((string) ($txn['_id'] ?? $txn['id'] ?? ''));
        $balanceAfter  = isset($txn['balance']) ? (int) abs((int) $txn['balance']) : null;

        // source_hash identifies this exact transaction for deduplication
        $sourceHash = sha1(implode('|', [
            $bankAccount->id,
            $lineReference !== '' ? $lineReference : $description,
            $postedAt->toDateString(),
            $amountKobo,
            $direction,
        ]));

        return [
            'company_id'       => $bankAccount->company_id,
            'bank_statement_id'=> $statement->id,
            'bank_account_id'  => $bankAccount->id,
            'line_reference'   => $lineReference !== '' ? $lineReference : null,
            'posted_at'        => $postedAt,
            'value_date'       => $postedAt->toDateString(),
            'description'      => $description !== '' ? $description : null,
            'direction'        => $direction,
            'amount'           => $amountKobo,
            'currency_code'    => strtoupper((string) ($txn['currency'] ?? $bankAccount->currency_code ?? 'NGN')),
            'balance_after'    => $balanceAfter,
            'source_hash'      => $sourceHash,
            'is_reconciled'    => false,
            'metadata'         => [
                'source'          => 'mono_connect',
                'mono_txn_id'     => $lineReference,
                'raw'             => $txn,
            ],
            'created_by'       => $actor->id,
            'updated_by'       => $actor->id,
        ];
    }
}
