<?php

namespace App\Services\AI;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Treasury\Models\ReconciliationException;
use Illuminate\Support\Carbon;

class TreasuryReconciliationFlowAgentService
{
    /**
     * Analyze a treasury reconciliation exception and suggest likely manual resolution path.
     *
     * @return array{
     *   risk_level:string,
     *   risk_score:int,
     *   confidence:int,
     *   suggested_match:string,
     *   suggested_match_type:string,
     *   why_blocked:string,
     *   next_action:string,
     *   summary:string,
     *   signals:array<int,string>,
     *   engine:string,
     *   generated_at:string
     * }
     */
    public function analyze(ReconciliationException $exception): array
    {
        $exception->loadMissing('line');

        $score = $this->severityBaseScore((string) $exception->severity);
        $signals = [];

        if ((string) $exception->exception_status === ReconciliationException::STATUS_OPEN) {
            $score += 10;
            $signals[] = 'Exception is still open and blocks reconciliation closeout.';
        }

        $ageHours = $exception->created_at ? (int) $exception->created_at->diffInHours(now()) : 0;
        if ($ageHours >= 48) {
            $score += 10;
            $signals[] = 'Exception age is '.$ageHours.'h and has crossed standard queue SLA.';
        }

        [$whyBlocked, $defaultAction] = $this->exceptionGuidance((string) $exception->exception_code);
        if ($whyBlocked !== '') {
            $signals[] = $whyBlocked;
        }

        $candidates = $this->candidatePreviewFromMetadata($exception);
        if ($candidates === []) {
            $candidates = $this->candidateSearchFromCurrentData($exception);
        }

        $best = $candidates[0] ?? null;
        if ($best && (float) ($best['confidence'] ?? 0) >= 65.0) {
            $signals[] = 'Best candidate confidence is '.(int) round((float) $best['confidence']).'%.';
            $score = max(0, $score - 8);
        } else {
            $signals[] = 'No strong candidate match reached the minimum confidence threshold.';
            $score += 6;
        }

        $score = max(0, min(100, $score));
        $riskLevel = $score >= 75 ? 'high' : ($score >= 45 ? 'medium' : 'low');

        $suggestedMatch = $best ? (string) $best['label'] : 'No strong candidate identified.';
        $suggestedMatchType = $best ? (string) $best['type'] : 'none';
        $confidence = $this->confidence($score, $candidates);
        $nextAction = $this->nextAction($best, $defaultAction);

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $score,
            'confidence' => $confidence,
            'suggested_match' => $suggestedMatch,
            'suggested_match_type' => $suggestedMatchType,
            'why_blocked' => $whyBlocked !== '' ? $whyBlocked : trim((string) ($exception->details ?? 'Manual treasury review is required before closing this exception.')),
            'next_action' => $nextAction,
            'summary' => $this->summary($riskLevel, $score, $suggestedMatch),
            'signals' => array_slice(array_values(array_unique(array_filter($signals))), 0, 4),
            'engine' => 'deterministic_treasury_reconciliation_rules',
            'generated_at' => now()->format('M d, Y H:i'),
        ];
    }

    private function severityBaseScore(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            ReconciliationException::SEVERITY_CRITICAL => 85,
            ReconciliationException::SEVERITY_HIGH => 70,
            ReconciliationException::SEVERITY_MEDIUM => 45,
            default => 20,
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function exceptionGuidance(string $exceptionCode): array
    {
        return match (strtolower(trim($exceptionCode))) {
            'conflict_multiple_targets' => [
                'Multiple targets match the same statement line with competing confidence signals.',
                'Review top two targets and resolve using the strongest evidence trail.',
            ],
            'low_confidence_match' => [
                'A possible match exists but confidence was below the auto-match policy threshold.',
                'Validate reference/date/amount and resolve only if evidence is sufficient.',
            ],
            'unmatched_statement_line' => [
                'No payout or expense record was confidently linked to this bank statement line.',
                'Check for missing payout attempt, missing expense evidence, or incorrect bank narration.',
            ],
            default => [
                '',
                'Review line reference, transaction date, and amount, then resolve or waive with clear note.',
            ],
        };
    }

    /**
     * @return array<int, array{type:string,label:string,confidence:float}>
     */
    private function candidatePreviewFromMetadata(ReconciliationException $exception): array
    {
        $preview = data_get((array) ($exception->metadata ?? []), 'candidate_preview', []);
        if (! is_array($preview)) {
            return [];
        }

        $mapped = [];
        foreach ($preview as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $type = $this->candidateTypeFromTargetType((string) ($candidate['target_type'] ?? ''));
            $targetId = (int) ($candidate['target_id'] ?? 0);
            $confidence = max(0.0, min(100.0, (float) ($candidate['confidence'] ?? 0)));
            $reason = trim((string) ($candidate['reason'] ?? ''));

            if ($targetId <= 0) {
                continue;
            }

            $mapped[] = [
                'type' => $type,
                'label' => strtoupper($type).' #'.$targetId.($reason !== '' ? ' - '.$reason : ''),
                'confidence' => $confidence,
            ];
        }

        usort($mapped, static fn (array $left, array $right): int => ((float) $right['confidence']) <=> ((float) $left['confidence']));

        return array_slice($mapped, 0, 3);
    }

    /**
     * @return array<int, array{type:string,label:string,confidence:float}>
     */
    private function candidateSearchFromCurrentData(ReconciliationException $exception): array
    {
        $line = $exception->line;
        if (! $line) {
            return [];
        }

        $lineDate = $line->value_date ? Carbon::parse($line->value_date) : Carbon::parse($line->posted_at);
        $referenceText = strtolower(trim((string) (($line->line_reference ?? '').' '.($line->description ?? ''))));
        $amount = (int) $line->amount;

        $amountTolerance = max(0, (int) config('ai.treasury_reconciliation.amount_tolerance', 0));
        $dateWindowDays = max(1, (int) config('ai.treasury_reconciliation.date_window_days', 3));

        $payoutMinAmount = max(0, $amount - $amountTolerance);
        $payoutMaxAmount = $amount + $amountTolerance;

        // Keep candidate query bounded and tenant-scoped so operator hints stay fast and safe.
        $payoutCandidates = RequestPayoutExecutionAttempt::query()
            ->where('company_id', (int) $exception->company_id)
            ->whereIn('execution_status', ['settled', 'webhook_pending', 'processing', 'queued'])
            ->whereBetween('amount', [$payoutMinAmount, $payoutMaxAmount])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (RequestPayoutExecutionAttempt $attempt) use ($lineDate, $dateWindowDays, $referenceText): ?array {
                $attemptDate = $attempt->settled_at ?: $attempt->processed_at ?: $attempt->queued_at ?: $attempt->created_at;
                if (! $attemptDate) {
                    return null;
                }

                $dateDiff = abs($lineDate->diffInDays(Carbon::parse($attemptDate), false));
                if ($dateDiff > $dateWindowDays) {
                    return null;
                }

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

                $confidence = $referenceHit ? 92.0 : (78.0 - min(15.0, $dateDiff * 4.0));
                $reference = trim((string) ($attempt->provider_reference ?: data_get((array) ($attempt->metadata ?? []), 'request_code', '')));

                return [
                    'type' => 'payout',
                    'label' => 'PAYOUT #'.(int) $attempt->id
                        .($reference !== '' ? ' ('.$reference.')' : '')
                        .' - '.str_replace('_', ' ', (string) $attempt->execution_status),
                    'confidence' => max(0.0, min(100.0, $confidence)),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $minDate = $lineDate->copy()->subDays($dateWindowDays)->toDateString();
        $maxDate = $lineDate->copy()->addDays($dateWindowDays)->toDateString();
        $expenseMinAmount = max(0, $amount - $amountTolerance);
        $expenseMaxAmount = $amount + $amountTolerance;

        $expenseCandidates = Expense::query()
            ->with('vendor:id,name')
            ->where('company_id', (int) $exception->company_id)
            ->where('status', '!=', 'void')
            ->whereBetween('expense_date', [$minDate, $maxDate])
            ->whereBetween('amount', [$expenseMinAmount, $expenseMaxAmount])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (Expense $expense) use ($lineDate, $referenceText): array {
                $expenseDate = $expense->expense_date ? Carbon::parse($expense->expense_date) : null;
                $dateDiff = $expenseDate ? abs($lineDate->diffInDays($expenseDate, false)) : 99;

                $searchText = strtolower(implode(' ', array_filter([
                    (string) $expense->expense_code,
                    (string) $expense->title,
                    (string) $expense->description,
                    (string) ($expense->vendor?->name ?? ''),
                ])));

                $textSimilarity = $this->tokenSimilarityPercent($referenceText, $searchText);
                $confidence = 58.0 + min(25.0, $textSimilarity * 0.25);
                if ($dateDiff <= 1) {
                    $confidence += 6.0;
                } elseif ($dateDiff <= 3) {
                    $confidence += 3.0;
                }

                return [
                    'type' => 'expense',
                    'label' => 'EXPENSE #'.(int) $expense->id.' - '.trim((string) ($expense->title ?: $expense->expense_code ?: 'record')),
                    'confidence' => max(0.0, min(100.0, $confidence)),
                ];
            })
            ->values()
            ->all();

        $combined = array_merge($payoutCandidates, $expenseCandidates);
        usort($combined, static fn (array $left, array $right): int => ((float) $right['confidence']) <=> ((float) $left['confidence']));

        return array_slice($combined, 0, 3);
    }

    private function candidateTypeFromTargetType(string $targetType): string
    {
        return match ($targetType) {
            RequestPayoutExecutionAttempt::class => 'payout',
            Expense::class => 'expense',
            default => 'record',
        };
    }

    /**
     * @param  array<int, array{type:string,label:string,confidence:float}>  $candidates
     */
    private function confidence(int $score, array $candidates): int
    {
        $best = (float) ($candidates[0]['confidence'] ?? 0.0);
        $base = 62 + min(20, count($candidates) * 5);

        if ($best >= 85.0) {
            $base += 8;
        } elseif ($best >= 70.0) {
            $base += 4;
        }

        if ($score >= 75) {
            $base += 4;
        }

        return max(50, min(96, $base));
    }

    /**
     * @param  array{type:string,label:string,confidence:float}|null  $best
     */
    private function nextAction(?array $best, string $defaultAction): string
    {
        if ($best && (float) ($best['confidence'] ?? 0.0) >= 80.0) {
            return 'Validate '.$best['label'].' against bank line reference/amount, then resolve with audit note.';
        }

        if ($best && (float) ($best['confidence'] ?? 0.0) >= 65.0) {
            return 'Review '.$best['label'].' plus one alternative candidate before deciding resolve or waive.';
        }

        return $defaultAction;
    }

    private function summary(string $riskLevel, int $score, string $suggestedMatch): string
    {
        return match ($riskLevel) {
            'high' => 'High reconciliation risk ('.$score.'/100). '.$suggestedMatch,
            'medium' => 'Moderate reconciliation risk ('.$score.'/100). '.$suggestedMatch,
            default => 'Low reconciliation risk ('.$score.'/100). '.$suggestedMatch,
        };
    }

    private function tokenSimilarityPercent(string $left, string $right): int
    {
        $leftTokens = $this->normalizedTokens($left);
        $rightTokens = $this->normalizedTokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0;
        }

        $common = count(array_intersect($leftTokens, $rightTokens));
        if ($common === 0) {
            return 0;
        }

        return (int) round(($common / max(1, min(count($leftTokens), count($rightTokens)))) * 100);
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

        return array_values(array_unique(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => strlen($token) >= 3,
        )));
    }
}
