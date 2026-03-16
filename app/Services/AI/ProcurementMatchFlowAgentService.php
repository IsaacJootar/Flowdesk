<?php

namespace App\Services\AI;

use App\Domains\Procurement\Models\InvoiceMatchException;
use Illuminate\Support\Carbon;

class ProcurementMatchFlowAgentService
{
    /**
     * Analyze a procurement match exception and produce operator guidance.
     *
     * @return array{
     *   risk_level:string,
     *   risk_score:int,
     *   confidence:int,
     *   why_blocked:string,
     *   top_risk:string,
     *   next_action:string,
     *   summary:string,
     *   signals:array<int,string>,
     *   engine:string,
     *   generated_at:string
     * }
     */
    public function analyze(InvoiceMatchException $exception): array
    {
        $exception->loadMissing(['order:id,po_number', 'invoice:id,invoice_number', 'matchResult:id,match_status,match_score']);

        $signals = [];
        $score = $this->severityBaseScore((string) $exception->severity);

        if ((string) $exception->exception_status === InvoiceMatchException::STATUS_OPEN) {
            $score += 10;
            $signals[] = 'Exception is still open and blocks match completion.';
        }

        $matchScore = (float) ($exception->matchResult?->match_score ?? 0);
        if ($matchScore > 0 && $matchScore <= 40) {
            $score += 15;
            $signals[] = 'Match confidence is very low (score '.number_format($matchScore, 2).').';
        } elseif ($matchScore > 40 && $matchScore <= 65) {
            $score += 8;
            $signals[] = 'Match confidence is below preferred threshold (score '.number_format($matchScore, 2).').';
        }

        $ageDays = max(0, (int) floor(Carbon::parse((string) $exception->created_at)->diffInDays(now())));
        if ($ageDays >= 7) {
            $score += 10;
            $signals[] = 'Exception has been open for '.$ageDays.' days.';
        } elseif ($ageDays >= 3) {
            $score += 5;
            $signals[] = 'Exception has remained unresolved for '.$ageDays.' days.';
        }

        $exceptionCode = strtolower(trim((string) $exception->exception_code));
        [$whyBlocked, $nextAction] = $this->codeGuidance($exceptionCode);

        if ($whyBlocked === '') {
            $whyBlocked = trim((string) ($exception->details ?? 'Match exception requires manual review before payout handoff.'));
        }
        if ($nextAction === '') {
            $nextAction = trim((string) data_get((array) ($exception->metadata ?? []), 'next_action', 'Review mismatch details and resolve or waive with a clear audit note.'));
        }

        if ($whyBlocked !== '') {
            $signals[] = $whyBlocked;
        }

        $score = max(0, min(100, $score));
        $riskLevel = $score >= 75 ? 'high' : ($score >= 45 ? 'medium' : 'low');

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $score,
            'confidence' => $this->confidence($signals, $riskLevel),
            'why_blocked' => $whyBlocked,
            'top_risk' => $signals[0] ?? 'No elevated risk signal detected.',
            'next_action' => $nextAction,
            'summary' => $this->summary($riskLevel, $score),
            'signals' => array_slice(array_values(array_unique(array_filter($signals))), 0, 4),
            'engine' => 'deterministic_procurement_rules',
            'generated_at' => now()->format('M d, Y H:i'),
        ];
    }

    private function severityBaseScore(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            InvoiceMatchException::SEVERITY_CRITICAL => 85,
            InvoiceMatchException::SEVERITY_HIGH => 70,
            InvoiceMatchException::SEVERITY_MEDIUM => 45,
            default => 20,
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function codeGuidance(string $exceptionCode): array
    {
        return match ($exceptionCode) {
            'no_receipt_recorded' => [
                'No receipt has been recorded against this PO, so 3-way match cannot pass.',
                'Record goods receipt for delivered quantities, then rerun invoice match.',
            ],
            'quantity_mismatch' => [
                'Invoice quantity does not align with PO and recorded receipt quantities.',
                'Confirm delivery quantity and either record remaining receipt or request corrected invoice.',
            ],
            'amount_mismatch' => [
                'Invoice total does not align with PO/receipt amount expectations.',
                'Verify item pricing/tax on PO and invoice, then correct source document before closing.',
            ],
            'date_outside_tolerance' => [
                'Invoice/receipt timing is outside configured tolerance for automatic match.',
                'Validate document dates and adjust tolerance policy only if business-approved.',
            ],
            'vendor_mismatch' => [
                'Vendor linkage is inconsistent across PO and invoice records.',
                'Relink invoice to the correct vendor/PO pair before resolving exception.',
            ],
            default => ['', ''],
        };
    }

    /**
     * @param  array<int,string>  $signals
     */
    private function confidence(array $signals, string $riskLevel): int
    {
        $base = 68 + min(18, count($signals) * 4);
        if ($riskLevel === 'high') {
            $base += 5;
        }

        return max(55, min(95, $base));
    }

    private function summary(string $riskLevel, int $score): string
    {
        return match ($riskLevel) {
            'high' => 'High exception risk ('.$score.'/100). Resolve root mismatch before payout handoff.',
            'medium' => 'Moderate exception risk ('.$score.'/100). Validate details carefully before closure.',
            default => 'Low exception risk ('.$score.'/100). Follow standard resolution checklist.',
        };
    }
}

