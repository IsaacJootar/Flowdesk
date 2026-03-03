<?php

namespace App\Services\Treasury;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Treasury\Models\ReconciliationMatch;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoReconcileStatementService
{
    public function __construct(
        private readonly TreasuryControlSettingsService $treasuryControlSettingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    /**
     * @return array{matched:int,exceptions:int,conflicts:int}
     *
     * @throws ValidationException
     */
    public function run(User $actor, BankStatement $statement): array
    {
        if ((int) $statement->company_id !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'statement' => 'Statement is outside your tenant scope.',
            ]);
        }

        $controls = $this->treasuryControlSettingsService->effectiveControls((int) $actor->company_id);
        $dateWindow = (int) ($controls['auto_match_date_window_days'] ?? 3);
        $amountTolerance = (int) ($controls['auto_match_amount_tolerance'] ?? 0);

        $matched = 0;
        $exceptions = 0;
        $conflicts = 0;

        BankStatementLine::query()
            ->where('bank_statement_id', (int) $statement->id)
            ->orderBy('id')
            ->chunkById(200, function ($lines) use (
                $actor,
                $dateWindow,
                $amountTolerance,
                &$matched,
                &$exceptions,
                &$conflicts
            ): void {
                foreach ($lines as $line) {
                    if ((bool) $line->is_reconciled) {
                        continue;
                    }

                    DB::transaction(function () use (
                        $actor,
                        $line,
                        $dateWindow,
                        $amountTolerance,
                        &$matched,
                        &$exceptions,
                        &$conflicts
                    ): void {
                        ReconciliationException::query()
                            ->where('bank_statement_line_id', (int) $line->id)
                            ->where('exception_status', ReconciliationException::STATUS_OPEN)
                            ->delete();

                        $candidates = array_merge(
                            $this->findPayoutCandidates($line, $dateWindow, $amountTolerance),
                            $this->findExpenseCandidates($line, $dateWindow, $amountTolerance)
                        );

                        if (count($candidates) === 1) {
                            $candidate = $candidates[0];

                            ReconciliationMatch::query()->updateOrCreate(
                                [
                                    'company_id' => (int) $line->company_id,
                                    'bank_statement_line_id' => (int) $line->id,
                                    'match_target_type' => (string) $candidate['target_type'],
                                    'match_target_id' => (int) $candidate['target_id'],
                                ],
                                [
                                    'match_stream' => (string) $candidate['stream'],
                                    'match_status' => ReconciliationMatch::STATUS_MATCHED,
                                    'confidence_score' => (float) $candidate['confidence'],
                                    'matched_by' => 'system',
                                    'matched_by_user_id' => null,
                                    'matched_at' => now(),
                                    'unmatched_at' => null,
                                    'unmatch_reason' => null,
                                    'metadata' => [
                                        'reason' => (string) $candidate['reason'],
                                        'auto_created' => true,
                                    ],
                                    'created_by' => (int) $actor->id,
                                    'updated_by' => (int) $actor->id,
                                ]
                            );

                            $line->forceFill([
                                'is_reconciled' => true,
                                'reconciled_at' => now(),
                                'updated_by' => (int) $actor->id,
                            ])->save();

                            $matched++;

                            return;
                        }

                        if (count($candidates) > 1) {
                            $this->openException(
                                actor: $actor,
                                line: $line,
                                code: 'conflict_multiple_targets',
                                severity: ReconciliationException::SEVERITY_HIGH,
                                stream: ReconciliationException::STREAM_EXECUTION_PAYMENT,
                                details: 'Multiple reconciliation targets matched this bank line.',
                                nextAction: 'Use manual reconciliation to select the correct target.',
                                metadata: [
                                    'candidate_count' => count($candidates),
                                    'auto_created' => true,
                                ],
                            );

                            $exceptions++;
                            $conflicts++;

                            return;
                        }

                        $this->openException(
                            actor: $actor,
                            line: $line,
                            code: 'unmatched_statement_line',
                            severity: ReconciliationException::SEVERITY_MEDIUM,
                            stream: ReconciliationException::STREAM_EXECUTION_PAYMENT,
                            details: 'No execution payout or expense record matched this bank line.',
                            nextAction: 'Review payment reference, amount, and transaction date, then match manually.',
                            metadata: [
                                'auto_created' => true,
                            ],
                        );

                        $exceptions++;
                    });
                }
            }, 'id');

        $this->tenantAuditLogger->log(
            companyId: (int) $actor->company_id,
            action: 'tenant.treasury.reconciliation.auto_run',
            actor: $actor,
            description: 'Auto-reconciliation run completed for imported statement.',
            entityType: BankStatement::class,
            entityId: (int) $statement->id,
            metadata: [
                'statement_reference' => (string) $statement->statement_reference,
                'matched' => $matched,
                'exceptions' => $exceptions,
                'conflicts' => $conflicts,
            ],
        );

        return [
            'matched' => $matched,
            'exceptions' => $exceptions,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>
     */
    private function findPayoutCandidates(BankStatementLine $line, int $dateWindow, int $amountTolerance): array
    {
        $lineDate = $line->value_date ? Carbon::parse($line->value_date) : Carbon::parse($line->posted_at);
        $referenceText = strtolower(trim((string) (($line->line_reference ?? '').' '.($line->description ?? ''))));

        return RequestPayoutExecutionAttempt::query()
            ->where('company_id', (int) $line->company_id)
            ->whereIn('execution_status', ['settled', 'webhook_pending'])
            ->get()
            ->filter(function (RequestPayoutExecutionAttempt $attempt) use ($line, $lineDate, $dateWindow, $amountTolerance): bool {
                $attemptAmount = (int) round((float) $attempt->amount);
                $amountDiff = abs($attemptAmount - (int) $line->amount);
                if ($amountDiff > $amountTolerance) {
                    return false;
                }

                $attemptDate = $attempt->settled_at ?: $attempt->processed_at ?: $attempt->queued_at ?: $attempt->created_at;
                if (! $attemptDate) {
                    return false;
                }

                return abs($lineDate->diffInDays(Carbon::parse($attemptDate), false)) <= $dateWindow;
            })
            ->map(function (RequestPayoutExecutionAttempt $attempt) use ($referenceText): array {
                $referenceHit = false;
                $referenceParts = array_filter([
                    strtolower((string) ($attempt->provider_reference ?? '')),
                    strtolower((string) ($attempt->idempotency_key ?? '')),
                    strtolower((string) data_get((array) ($attempt->metadata ?? []), 'request_code', '')),
                ]);

                foreach ($referenceParts as $part) {
                    if ($part !== '' && str_contains($referenceText, $part)) {
                        $referenceHit = true;
                        break;
                    }
                }

                return [
                    'target_type' => RequestPayoutExecutionAttempt::class,
                    'target_id' => (int) $attempt->id,
                    'stream' => ReconciliationMatch::STREAM_EXECUTION_PAYMENT,
                    'confidence' => $referenceHit ? 95.0 : 78.0,
                    'reason' => $referenceHit
                        ? 'Matched payout by amount/date/reference.'
                        : 'Matched payout by amount/date window.',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>
     */
    private function findExpenseCandidates(BankStatementLine $line, int $dateWindow, int $amountTolerance): array
    {
        $lineDate = $line->value_date ? Carbon::parse($line->value_date) : Carbon::parse($line->posted_at);
        $referenceText = strtolower(trim((string) (($line->line_reference ?? '').' '.($line->description ?? ''))));

        return Expense::query()
            ->where('company_id', (int) $line->company_id)
            ->where('status', '!=', 'void')
            ->get()
            ->filter(function (Expense $expense) use ($line, $lineDate, $dateWindow, $amountTolerance): bool {
                $amountDiff = abs((int) $expense->amount - (int) $line->amount);
                if ($amountDiff > $amountTolerance) {
                    return false;
                }

                if (! $expense->expense_date) {
                    return false;
                }

                return abs($lineDate->diffInDays(Carbon::parse($expense->expense_date), false)) <= $dateWindow;
            })
            ->map(function (Expense $expense) use ($referenceText): array {
                $title = strtolower((string) ($expense->title ?? ''));
                $description = strtolower((string) ($expense->description ?? ''));
                $textHit = ($title !== '' && str_contains($referenceText, $title))
                    || ($description !== '' && str_contains($referenceText, $description));

                return [
                    'target_type' => Expense::class,
                    'target_id' => (int) $expense->id,
                    'stream' => ReconciliationMatch::STREAM_EXPENSE_EVIDENCE,
                    'confidence' => $textHit ? 82.0 : 68.0,
                    'reason' => $textHit
                        ? 'Matched direct expense by amount/date and description text.'
                        : 'Matched direct expense by amount/date window.',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function openException(
        User $actor,
        BankStatementLine $line,
        string $code,
        string $severity,
        string $stream,
        string $details,
        string $nextAction,
        array $metadata = [],
    ): void {
        ReconciliationException::query()->create([
            'company_id' => (int) $line->company_id,
            'bank_statement_line_id' => (int) $line->id,
            'reconciliation_match_id' => null,
            'exception_code' => $code,
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => $severity,
            'match_stream' => $stream,
            'next_action' => $nextAction,
            'details' => $details,
            'metadata' => $metadata,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);
    }
}