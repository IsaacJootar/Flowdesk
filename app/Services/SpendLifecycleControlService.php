<?php

namespace App\Services;

use App\Domains\Expenses\Models\CompanyExpensePolicySetting;
use App\Domains\Requests\Models\CompanyRequestPolicySetting;

class SpendLifecycleControlService
{
    public const BUDGET_OFF = 'off';
    public const BUDGET_WARN = 'warn';
    public const BUDGET_BLOCK_MISSING = 'block_missing';
    public const BUDGET_BLOCK_MISSING_OR_OVER = 'block_missing_or_over';

    public const HANDOFF_MANUAL = 'manual';
    public const HANDOFF_FINANCE_REVIEW = 'finance_review';
    public const HANDOFF_AUTO_CREATE = 'auto_create';

    public const RECEIPT_OPTIONAL = 'optional';
    public const RECEIPT_REQUIRED = 'required';

    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function settingsForCompany(int $companyId): array
    {
        $expenseSetting = CompanyExpensePolicySetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyExpensePolicySetting::defaultAttributes()
            );
        $requestSetting = CompanyRequestPolicySetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyRequestPolicySetting::defaultAttributes()
            );

        $configured = (array) data_get((array) ($expenseSetting->metadata ?? []), 'spend_lifecycle', []);
        $defaults = $this->defaults((string) $requestSetting->budget_guardrail_mode);

        return array_replace($defaults, array_intersect_key($configured, $defaults));
    }

    /**
     * @param  array<string,mixed>  $input
     */
    public function saveForCompany(int $companyId, array $input, ?int $actorUserId = null): void
    {
        $setting = CompanyExpensePolicySetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyExpensePolicySetting::defaultAttributes()
            );

        $metadata = (array) ($setting->metadata ?? []);
        $metadata['spend_lifecycle'] = [
            'budget_control_mode' => $this->normalizeBudgetMode((string) ($input['budget_control_mode'] ?? self::BUDGET_WARN)),
            'expense_handoff_mode' => $this->normalizeHandoffMode((string) ($input['expense_handoff_mode'] ?? self::HANDOFF_FINANCE_REVIEW)),
            'direct_expense_receipt_mode' => $this->normalizeReceiptMode((string) ($input['direct_expense_receipt_mode'] ?? self::RECEIPT_OPTIONAL)),
            'direct_expense_receipt_threshold' => max(0, (int) ($input['direct_expense_receipt_threshold'] ?? 0)),
            'direct_expense_reason_required' => (bool) ($input['direct_expense_reason_required'] ?? false),
            'finance_override_requires_reason' => (bool) ($input['finance_override_requires_reason'] ?? true),
        ];

        $setting->forceFill([
            'metadata' => $metadata,
            'updated_by' => $actorUserId,
        ])->save();

        $this->activityLogger->log(
            action: 'expense.lifecycle_controls.updated',
            entityType: CompanyExpensePolicySetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'spend_lifecycle' => $metadata['spend_lifecycle'],
            ],
            companyId: $companyId,
            userId: $actorUserId,
        );
    }

    /**
     * @param  array<string,mixed>  $guardrail
     * @return array{allowed: bool, severity: string, message: string, mode: string}
     */
    public function budgetDecision(int $companyId, array $guardrail, string $context): array
    {
        $settings = $this->settingsForCompany($companyId);
        $mode = $this->normalizeBudgetMode((string) ($settings['budget_control_mode'] ?? self::BUDGET_WARN));
        $hasBudget = (bool) ($guardrail['has_budget'] ?? false);
        $isExceeded = (bool) (($guardrail['is_exceeded'] ?? false) || ($guardrail['is_blocked'] ?? false));
        $overAmount = (int) ($guardrail['over_amount'] ?? 0);

        if ($mode === self::BUDGET_OFF) {
            return [
                'allowed' => true,
                'severity' => 'none',
                'message' => '',
                'mode' => $mode,
            ];
        }

        if (! $hasBudget && in_array($mode, [self::BUDGET_BLOCK_MISSING, self::BUDGET_BLOCK_MISSING_OR_OVER], true)) {
            return [
                'allowed' => false,
                'severity' => 'high',
                'message' => $this->budgetMessage('missing_blocked', $context, 0),
                'mode' => $mode,
            ];
        }

        if ($isExceeded && $mode === self::BUDGET_BLOCK_MISSING_OR_OVER) {
            return [
                'allowed' => false,
                'severity' => 'high',
                'message' => $this->budgetMessage('over_blocked', $context, $overAmount),
                'mode' => $mode,
            ];
        }

        if (! $hasBudget) {
            return [
                'allowed' => true,
                'severity' => 'medium',
                'message' => $this->budgetMessage('missing_warning', $context, 0),
                'mode' => $mode,
            ];
        }

        if ($isExceeded) {
            return [
                'allowed' => true,
                'severity' => 'medium',
                'message' => $this->budgetMessage('over_warning', $context, $overAmount),
                'mode' => $mode,
            ];
        }

        return [
            'allowed' => true,
            'severity' => 'none',
            'message' => '',
            'mode' => $mode,
        ];
    }

    public function expenseHandoffMode(int $companyId): string
    {
        return $this->normalizeHandoffMode((string) ($this->settingsForCompany($companyId)['expense_handoff_mode'] ?? self::HANDOFF_FINANCE_REVIEW));
    }

    public function directExpenseReceiptRequired(int $companyId, int $amount): bool
    {
        $settings = $this->settingsForCompany($companyId);
        if ($this->normalizeReceiptMode((string) ($settings['direct_expense_receipt_mode'] ?? self::RECEIPT_OPTIONAL)) !== self::RECEIPT_REQUIRED) {
            return false;
        }

        $threshold = max(0, (int) ($settings['direct_expense_receipt_threshold'] ?? 0));

        return $threshold < 1 || $amount >= $threshold;
    }

    public function directExpenseReasonRequired(int $companyId): bool
    {
        return (bool) ($this->settingsForCompany($companyId)['direct_expense_reason_required'] ?? false);
    }

    /**
     * @return array<string,mixed>
     */
    private function defaults(string $legacyRequestBudgetMode): array
    {
        return [
            'budget_control_mode' => self::BUDGET_WARN,
            'expense_handoff_mode' => self::HANDOFF_FINANCE_REVIEW,
            'direct_expense_receipt_mode' => self::RECEIPT_OPTIONAL,
            'direct_expense_receipt_threshold' => 0,
            'direct_expense_reason_required' => false,
            'finance_override_requires_reason' => true,
        ];
    }

    public function normalizeBudgetMode(string $mode): string
    {
        return in_array($mode, [self::BUDGET_OFF, self::BUDGET_WARN, self::BUDGET_BLOCK_MISSING, self::BUDGET_BLOCK_MISSING_OR_OVER], true)
            ? $mode
            : self::BUDGET_WARN;
    }

    public function normalizeHandoffMode(string $mode): string
    {
        return in_array($mode, [self::HANDOFF_MANUAL, self::HANDOFF_FINANCE_REVIEW, self::HANDOFF_AUTO_CREATE], true)
            ? $mode
            : self::HANDOFF_FINANCE_REVIEW;
    }

    public function normalizeReceiptMode(string $mode): string
    {
        return $mode === self::RECEIPT_REQUIRED ? self::RECEIPT_REQUIRED : self::RECEIPT_OPTIONAL;
    }

    private function budgetMessage(string $kind, string $context, int $overAmount): string
    {
        $contextLabel = match ($context) {
            'request_submission' => 'Request submission',
            'final_approval' => 'Final approval',
            'payout_queueing' => 'Payout queueing',
            'expense_posting' => 'Expense posting',
            default => 'Spend action',
        };

        return match ($kind) {
            'missing_blocked' => $contextLabel.' is blocked because no active budget exists for this department period.',
            'over_blocked' => sprintf('%s is blocked because projected spend is over budget by NGN %s.', $contextLabel, number_format($overAmount)),
            'missing_warning' => $contextLabel.' has no active budget for this department period.',
            'over_warning' => sprintf('%s is over budget by NGN %s.', $contextLabel, number_format($overAmount)),
            default => '',
        };
    }
}
