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
     *   mismatch_label:string,
     *   signals:array<int,string>,
     *   engine:string,
     *   generated_at:string
     * }
     */
    public function analyze(InvoiceMatchException $exception): array
    {
        $exception->loadMissing([
            'order:id,po_number',
            'invoice:id,invoice_number,total_amount,currency',
            'matchResult:id,match_status,match_score,metadata',
        ]);

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

        // Extract amount context from match result inputs for richer guidance.
        $inputs = (array) data_get((array) ($exception->matchResult?->metadata ?? []), 'inputs', []);
        $currency = strtoupper((string) ($exception->invoice?->currency ?: 'NGN'));
        $poAmount = isset($inputs['po_amount']) ? (int) $inputs['po_amount'] : null;
        $invoiceAmount = isset($inputs['invoice_amount']) ? (int) $inputs['invoice_amount'] : null;
        $amountContext = ($poAmount !== null && $invoiceAmount !== null)
            ? ['currency' => $currency, 'po_amount' => $poAmount, 'invoice_amount' => $invoiceAmount]
            : null;

        $exceptionCode = strtolower(trim((string) $exception->exception_code));
        [$whyBlocked, $nextAction] = $this->codeGuidance($exceptionCode, $amountContext);

        if ($whyBlocked === '') {
            $whyBlocked = trim((string) ($exception->details ?? 'Match exception requires manual review before payout handoff.'));
        }
        if ($nextAction === '') {
            $nextAction = trim((string) data_get((array) ($exception->metadata ?? []), 'next_action', 'Review mismatch details and resolve or waive with a clear audit note.'));
        }

        if ($whyBlocked !== '') {
            $signals[] = $whyBlocked;
        }

        // Build a short mismatch label and add a formatted amount signal when we have the raw numbers.
        $mismatchLabel = '';
        if ($amountContext !== null && $poAmount !== null && $invoiceAmount !== null) {
            $diff = abs($invoiceAmount - $poAmount);
            $direction = $invoiceAmount > $poAmount ? 'over' : 'under';
            $mismatchLabel = sprintf('%s %s %s PO amount', $currency, number_format($diff), $direction);
            $signals[] = sprintf('Amount gap: %s %s %s the PO amount.', $currency, number_format($diff), $direction);
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
            'mismatch_label' => $mismatchLabel,
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
     * @param  array{currency:string,po_amount:int,invoice_amount:int}|null  $amountContext
     * @return array{0:string,1:string}
     */
    private function codeGuidance(string $exceptionCode, ?array $amountContext = null): array
    {
        return match ($exceptionCode) {
            'no_receipt_recorded' => [
                'No receipt has been recorded against this PO, so 3-way match cannot pass.',
                'Record goods receipt for delivered quantities, then rerun invoice match.',
            ],
            'quantity_mismatch' => [
                'Invoice quantity does not align with PO and recorded receipt quantities.',
                'Confirm delivery quantity and either record remaining receipt or request a corrected invoice.',
            ],
            'amount_mismatch' => $this->amountMismatchGuidance($amountContext),
            'date_outside_tolerance', 'invoice_date_out_of_window' => [
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
     * @param  array{currency:string,po_amount:int,invoice_amount:int}|null  $amountContext
     * @return array{0:string,1:string}
     */
    private function amountMismatchGuidance(?array $amountContext): array
    {
        if ($amountContext === null) {
            return [
                'Invoice total does not align with PO/receipt amount expectations.',
                'Verify item pricing/tax on PO and invoice, then correct source document before closing.',
            ];
        }

        $currency = (string) $amountContext['currency'];
        $poAmt = (int) $amountContext['po_amount'];
        $invAmt = (int) $amountContext['invoice_amount'];
        $diff = abs($invAmt - $poAmt);
        $direction = $invAmt > $poAmt ? 'higher' : 'lower';

        return [
            sprintf(
                'Invoice amount (%s %s) is %s %s %s than the PO amount (%s %s).',
                $currency,
                number_format($invAmt),
                $currency,
                number_format($diff),
                $direction,
                $currency,
                number_format($poAmt)
            ),
            sprintf(
                'Correct the invoice to match the PO total of %s %s, or amend the PO if the price genuinely changed.',
                $currency,
                number_format($poAmt)
            ),
        ];
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
