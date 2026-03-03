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
        $minConfidence = (int) ($controls['auto_match_min_confidence'] ?? 75);
        $expenseTextSimilarityThreshold = (int) ($controls['direct_expense_text_similarity_threshold'] ?? 55);

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
                $minConfidence,
                $expenseTextSimilarityThreshold,
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
                        $minConfidence,
                        $expenseTextSimilarityThreshold,
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
                            $this->findExpenseCandidates($line, $dateWindow, $amountTolerance, $expenseTextSimilarityThreshold)
                        );

                        $selectedCandidate = $this->selectAutoMatchCandidate($candidates, $minConfidence);
                        if ($selectedCandidate !== null) {
                            ReconciliationMatch::query()->updateOrCreate(
                                [
                                    'company_id' => (int) $line->company_id,
                                    'bank_statement_line_id' => (int) $line->id,
                                    'match_target_type' => (string) $selectedCandidate['target_type'],
                                    'match_target_id' => (int) $selectedCandidate['target_id'],
                                ],
                                [
                                    'match_stream' => (string) $selectedCandidate['stream'],
                                    'match_status' => ReconciliationMatch::STATUS_MATCHED,
                                    'confidence_score' => (float) $selectedCandidate['confidence'],
                                    'matched_by' => 'system',
                                    'matched_by_user_id' => null,
                                    'matched_at' => now(),
                                    'unmatched_at' => null,
                                    'unmatch_reason' => null,
                                    'metadata' => [
                                        'reason' => (string) $selectedCandidate['reason'],
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
                            $ranked = $this->rankCandidatesByConfidence($candidates);
                            $bestConfidence = (float) data_get($ranked, '0.confidence', 0.0);
                            $secondConfidence = (float) data_get($ranked, '1.confidence', 0.0);

                            $details = $bestConfidence < $minConfidence
                                ? 'Multiple potential reconciliation targets found, but best confidence is below auto-match threshold.'
                                : 'Multiple reconciliation targets matched this bank line with similar confidence.';

                            // Safety control: keep ambiguous or weak multi-candidate rows in manual queue.
                            $this->openException(
                                actor: $actor,
                                line: $line,
                                code: 'conflict_multiple_targets',
                                severity: ReconciliationException::SEVERITY_HIGH,
                                stream: ReconciliationException::STREAM_EXECUTION_PAYMENT,
                                details: $details,
                                nextAction: 'Use manual reconciliation to select the correct target.',
                                metadata: [
                                    'candidate_count' => count($candidates),
                                    'min_confidence' => $minConfidence,
                                    'best_confidence' => round($bestConfidence, 2),
                                    'second_confidence' => round($secondConfidence, 2),
                                    'candidate_preview' => array_slice($this->candidatePreview($ranked), 0, 5),
                                    'auto_created' => true,
                                ],
                            );

                            $exceptions++;
                            $conflicts++;

                            return;
                        }

                        if (count($candidates) === 1) {
                            $single = $candidates[0];
                            $singleConfidence = (float) ($single['confidence'] ?? 0.0);

                            $this->openException(
                                actor: $actor,
                                line: $line,
                                code: 'low_confidence_match',
                                severity: ReconciliationException::SEVERITY_MEDIUM,
                                stream: (string) ($single['stream'] ?? ReconciliationException::STREAM_EXPENSE_EVIDENCE),
                                details: sprintf(
                                    'One potential reconciliation target was found, but confidence %.1f%% is below threshold %d%%.',
                                    $singleConfidence,
                                    $minConfidence,
                                ),
                                nextAction: 'Review evidence and apply manual reconciliation if the target is valid.',
                                metadata: [
                                    'min_confidence' => $minConfidence,
                                    'best_confidence' => round($singleConfidence, 2),
                                    'candidate_preview' => $this->candidatePreview([$single]),
                                    'auto_created' => true,
                                ],
                            );

                            $exceptions++;

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
                                'min_confidence' => $minConfidence,
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
                'controls' => [
                    'auto_match_date_window_days' => $dateWindow,
                    'auto_match_amount_tolerance' => $amountTolerance,
                    'auto_match_min_confidence' => $minConfidence,
                    'direct_expense_text_similarity_threshold' => $expenseTextSimilarityThreshold,
                ],
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
    private function findExpenseCandidates(BankStatementLine $line, int $dateWindow, int $amountTolerance, int $textSimilarityThreshold): array
    {
        $lineDate = $line->value_date ? Carbon::parse($line->value_date) : Carbon::parse($line->posted_at);
        $referenceText = strtolower(trim((string) (($line->line_reference ?? '').' '.($line->description ?? ''))));

        $minDate = $lineDate->copy()->subDays($dateWindow)->toDateString();
        $maxDate = $lineDate->copy()->addDays($dateWindow)->toDateString();
        $minAmount = max(0, (int) $line->amount - $amountTolerance);
        $maxAmount = (int) $line->amount + $amountTolerance;

        return Expense::query()
            ->with('vendor:id,name')
            ->where('company_id', (int) $line->company_id)
            ->where('status', '!=', 'void')
            ->whereBetween('expense_date', [$minDate, $maxDate])
            ->whereBetween('amount', [$minAmount, $maxAmount])
            ->get()
            ->map(function (Expense $expense) use ($line, $lineDate, $referenceText, $textSimilarityThreshold): array {
                $amountDiff = abs((int) $expense->amount - (int) $line->amount);
                $expenseDate = $expense->expense_date ? Carbon::parse($expense->expense_date) : null;
                $dateDiff = $expenseDate ? abs($lineDate->diffInDays($expenseDate, false)) : 999;

                $expenseCode = strtolower((string) ($expense->expense_code ?? ''));
                $expenseCodeHit = $expenseCode !== '' && str_contains($referenceText, $expenseCode);

                $merchantText = implode(' ', array_filter([
                    strtolower((string) ($expense->vendor?->name ?? '')),
                    strtolower((string) ($expense->title ?? '')),
                    strtolower((string) ($expense->description ?? '')),
                ]));

                $textSimilarity = $this->tokenSimilarityPercent($referenceText, $merchantText);
                $strongSignal = $expenseCodeHit || $textSimilarity >= $textSimilarityThreshold;
                $confidence = $this->expenseConfidence(
                    textSimilarity: $textSimilarity,
                    expenseCodeHit: $expenseCodeHit,
                    dateDiffDays: $dateDiff,
                    amountDiff: $amountDiff,
                    strongSignal: $strongSignal,
                );

                return [
                    'target_type' => Expense::class,
                    'target_id' => (int) $expense->id,
                    'stream' => ReconciliationMatch::STREAM_EXPENSE_EVIDENCE,
                    'confidence' => $confidence,
                    'reason' => $expenseCodeHit
                        ? 'Matched direct expense by amount/date and expense code reference.'
                        : ($strongSignal
                            ? 'Matched direct expense by amount/date and merchant text similarity.'
                            : 'Candidate expense by amount/date; merchant text similarity below threshold.'),
                ];
            })
            ->values()
            ->all();
    }

    private function expenseConfidence(int $textSimilarity, bool $expenseCodeHit, int $dateDiffDays, int $amountDiff, bool $strongSignal): float
    {
        $confidence = 52.0;

        if ($expenseCodeHit) {
            $confidence += 25.0;
        }

        $confidence += min(18.0, round($textSimilarity * 0.25, 2));

        if ($dateDiffDays <= 1) {
            $confidence += 8.0;
        } elseif ($dateDiffDays <= 3) {
            $confidence += 4.0;
        }

        if ($amountDiff === 0) {
            $confidence += 6.0;
        }

        if ($strongSignal && $dateDiffDays <= 1) {
            $confidence += 4.0;
        }

        return max(35.0, min(97.0, $confidence));
    }

    /**
     * @param  array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>  $candidates
     * @return array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}|null
     */
    private function selectAutoMatchCandidate(array $candidates, int $minConfidence): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $ranked = $this->rankCandidatesByConfidence($candidates);
        $top = $ranked[0] ?? null;
        if (! $top) {
            return null;
        }

        if ((float) $top['confidence'] < $minConfidence) {
            return null;
        }

        $second = $ranked[1] ?? null;
        // Ambiguity guardrail: require a confidence gap before auto-selecting among multiple candidates.
        if ($second && (((float) $top['confidence']) - ((float) $second['confidence'])) < 10.0) {
            return null;
        }

        return $top;
    }

    /**
     * @param  array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>  $candidates
     * @return array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>
     */
    private function rankCandidatesByConfidence(array $candidates): array
    {
        usort($candidates, function (array $left, array $right): int {
            return ((float) $right['confidence']) <=> ((float) $left['confidence']);
        });

        return $candidates;
    }

    private function tokenSimilarityPercent(string $left, string $right): int
    {
        $leftTokens = $this->normalizedTokens($left);
        $rightTokens = $this->normalizedTokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0;
        }

        $commonCount = count(array_intersect($leftTokens, $rightTokens));
        if ($commonCount === 0) {
            return 0;
        }

        // Bank narrations are often terse; measure overlap against the shorter token set.
        $baseCount = min(count($leftTokens), count($rightTokens));

        return (int) round(($commonCount / max(1, $baseCount)) * 100);
    }

    /**
     * @return array<int, string>
     */
    private function normalizedTokens(string $value): array
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $value)));
        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => strlen($token) >= 3,
        ))));
    }

    /**
     * @param  array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>  $candidates
     * @return array<int, array{target_type:class-string,target_id:int,stream:string,confidence:float,reason:string}>
     */
    private function candidatePreview(array $candidates): array
    {
        return array_map(static function (array $candidate): array {
            return [
                'target_type' => (string) $candidate['target_type'],
                'target_id' => (int) $candidate['target_id'],
                'stream' => (string) $candidate['stream'],
                'confidence' => round((float) $candidate['confidence'], 2),
                'reason' => (string) $candidate['reason'],
            ];
        }, $candidates);
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

