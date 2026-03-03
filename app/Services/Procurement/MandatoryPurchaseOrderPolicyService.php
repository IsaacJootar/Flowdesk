<?php

namespace App\Services\Procurement;

use App\Domains\Requests\Models\SpendRequest;

class MandatoryPurchaseOrderPolicyService
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService,
    ) {
    }

    /**
     * @return array{required:bool,reason:string,context:array<string,mixed>}
     */
    public function evaluateForRequest(SpendRequest $request): array
    {
        $controls = $this->settingsService->effectiveControls((int) $request->company_id);

        if (! (bool) ($controls['mandatory_po_enabled'] ?? false)) {
            return [
                'required' => false,
                'reason' => '',
                'context' => ['mode' => 'mandatory_po_disabled'],
            ];
        }

        $request->loadMissing('items:id,request_id,category');

        $amount = (int) ($request->approved_amount ?: $request->amount);
        $minimumAmount = max(0, (int) ($controls['mandatory_po_min_amount'] ?? 0));
        $policyCategories = $this->normalizeCodes((array) ($controls['mandatory_po_category_codes'] ?? []));
        $requestCategories = $this->normalizeCodes((array) $request->items->pluck('category')->all());

        $amountTriggered = $minimumAmount > 0 && $amount >= $minimumAmount;
        $categoryMatches = array_values(array_intersect($policyCategories, $requestCategories));
        $categoryTriggered = $categoryMatches !== [];

        if (! $amountTriggered && ! $categoryTriggered) {
            return [
                'required' => false,
                'reason' => '',
                'context' => [
                    'mode' => 'mandatory_po_not_triggered',
                    'minimum_amount' => $minimumAmount,
                    'amount' => $amount,
                    'policy_categories' => $policyCategories,
                    'request_categories' => $requestCategories,
                ],
            ];
        }

        $triggers = [];
        if ($amountTriggered) {
            $triggers[] = sprintf('amount %d >= threshold %d', $amount, $minimumAmount);
        }

        if ($categoryTriggered) {
            $triggers[] = sprintf('category match [%s]', implode(', ', $categoryMatches));
        }

        // Policy intent: enforce PO lane before any downstream non-PO handoff when governance conditions match.
        return [
            'required' => true,
            'reason' => sprintf(
                'Mandatory PO policy requires conversion before non-PO handoff (%s).',
                implode('; ', $triggers)
            ),
            'context' => [
                'mode' => 'mandatory_po_required',
                'minimum_amount' => $minimumAmount,
                'amount' => $amount,
                'policy_categories' => $policyCategories,
                'request_categories' => $requestCategories,
                'matched_categories' => $categoryMatches,
                'amount_triggered' => $amountTriggered,
                'category_triggered' => $categoryTriggered,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $codes
     * @return array<int, string>
     */
    private function normalizeCodes(array $codes): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $code): string => strtolower(trim((string) $code)),
            $codes
        )));
    }
}