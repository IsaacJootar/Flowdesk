<?php

namespace App\Services\AI;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use Illuminate\Support\Carbon;

class PayoutRiskFlowAgentService
{
    /**
     * Analyze payout execution risk for a single tenant-scoped request.
     *
     * @return array{
     *     risk_level:string,
     *     risk_score:int,
     *     confidence:int,
     *     top_reason:string,
     *     summary:string,
     *     guidance:string,
     *     signals:array<int,string>,
     *     engine:string,
     *     generated_at:string
     * }
     */
    public function analyze(SpendRequest $request): array
    {
        $request->loadMissing(['company.subscription', 'payoutExecutionAttempt']);

        $companyId = (int) $request->company_id;
        $requestAmount = (float) ($request->approved_amount ?: $request->amount ?: 0);
        $metadata = (array) ($request->metadata ?? []);
        $attempt = $request->payoutExecutionAttempt;

        $signals = [];
        $score = 0;

        if ((bool) data_get($metadata, 'execution.procurement_gate.blocked', false)) {
            $score += 45;
            $signals[] = 'Procurement gate is currently blocking payout queueing.';
        }

        if ((string) $request->status === 'failed' || (string) ($attempt?->execution_status ?? '') === 'failed') {
            $score += 30;
            $signals[] = 'Request already has a failed payout outcome.';
        }

        $sameRequestFailedCount = (int) RequestPayoutExecutionAttempt::query()
            ->where('company_id', $companyId)
            ->where('request_id', (int) $request->id)
            ->where('execution_status', 'failed')
            ->count();
        if ($sameRequestFailedCount > 0) {
            $score += min(20, $sameRequestFailedCount * 8);
            $signals[] = 'This request has '.$sameRequestFailedCount.' failed payout attempt(s).';
        }

        $highAmountThreshold = max(50000, (int) config('ai.payout_risk.high_amount_threshold', 1000000));
        if ($requestAmount >= $highAmountThreshold) {
            $score += 15;
            $signals[] = 'Approved amount is above configured risk threshold ('.number_format($highAmountThreshold).').';
        }

        if ($attempt && $attempt->queued_at) {
            $queueAgeMinutes = max(0, (int) floor(Carbon::parse((string) $attempt->queued_at)->diffInMinutes(now())));
            if ($queueAgeMinutes >= 120) {
                $score += 10;
                $signals[] = 'Payout has been queued for '.$queueAgeMinutes.' minutes.';
            }
        }

        $recentWindowStart = Carbon::now()->subDays(7);
        $recentAttempts = RequestPayoutExecutionAttempt::query()
            ->where('company_id', $companyId)
            ->where('updated_at', '>=', $recentWindowStart);
        $recentTotal = (int) (clone $recentAttempts)->count();
        $recentFailed = (int) (clone $recentAttempts)->where('execution_status', 'failed')->count();

        if ($recentTotal >= 5) {
            $recentFailureRate = round(($recentFailed / $recentTotal) * 100, 1);
            if ($recentFailureRate >= 30.0) {
                $score += 20;
                $signals[] = 'Tenant payout failure rate is '.$recentFailureRate.'% in the last 7 days.';
            } elseif ($recentFailureRate >= 15.0) {
                $score += 10;
                $signals[] = 'Tenant payout failure rate is elevated at '.$recentFailureRate.'% in the last 7 days.';
            }
        }

        $subscription = $request->company?->subscription;
        if (! $subscription) {
            $score += 30;
            $signals[] = 'Tenant subscription for execution policy is missing.';
        } elseif ((string) $subscription->payment_execution_mode !== 'execution_enabled') {
            $score += 20;
            $signals[] = 'Tenant execution mode is not fully enabled.';
        } elseif (trim((string) $subscription->execution_provider) === '') {
            $score += 25;
            $signals[] = 'Execution provider is not configured for this tenant.';
        }

        $score = max(0, min(100, $score));
        $riskLevel = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');

        if ($signals === []) {
            $signals[] = 'No elevated payout risk signals detected from current request and tenant execution history.';
        }

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $score,
            'confidence' => $this->confidence($score, $signals),
            'top_reason' => (string) $signals[0],
            'summary' => $this->summary($riskLevel, $score),
            'guidance' => $this->guidance($riskLevel),
            'signals' => array_slice($signals, 0, 4),
            'engine' => 'deterministic_risk_rules',
            'generated_at' => now()->format('M d, Y H:i'),
        ];
    }

    /**
     * @param  array<int,string>  $signals
     */
    private function confidence(int $score, array $signals): int
    {
        $base = 70 + min(20, count($signals) * 4);
        if ($score >= 70) {
            $base += 5;
        }

        return max(50, min(95, $base));
    }

    private function summary(string $riskLevel, int $score): string
    {
        return match ($riskLevel) {
            'high' => 'High payout risk detected (score '.$score.'/100). Review risk signals before running payout.',
            'medium' => 'Moderate payout risk detected (score '.$score.'/100). Proceed with caution and monitor outcomes.',
            default => 'Low payout risk detected (score '.$score.'/100). No major blockers were identified.',
        };
    }

    private function guidance(string $riskLevel): string
    {
        return match ($riskLevel) {
            'high' => 'Open Payment Provider Health first, confirm provider/runtime status, then send payment when blockers are addressed.',
            'medium' => 'Send payment with monitoring and capture incident notes quickly if status degrades to failed.',
            default => 'Proceed with the standard payment run and continue normal provider monitoring.',
        };
    }
}
