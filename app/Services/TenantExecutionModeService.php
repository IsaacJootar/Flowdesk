<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class TenantExecutionModeService
{
    public const MODE_DECISION_ONLY = 'decision_only';

    public const MODE_EXECUTION_ENABLED = 'execution_enabled';

    public function __construct(
        private readonly PaymentAuthorizationWorkflowResolver $paymentAuthorizationWorkflowResolver
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function supportedModes(): array
    {
        return [
            self::MODE_DECISION_ONLY,
            self::MODE_EXECUTION_ENABLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function supportedChannels(): array
    {
        return [
            'bank_transfer',
            'wallet_payout',
            'card_charge',
        ];
    }

    /**
     * @param  array<string, mixed>  $entitlements
     * @param  array<int, string>|string|null  $allowedChannels
     * @return array{
     *  payment_execution_mode:string,
     *  execution_provider:?string,
     *  execution_max_transaction_amount:?float,
     *  execution_daily_cap_amount:?float,
     *  execution_monthly_cap_amount:?float,
     *  execution_maker_checker_threshold_amount:?float,
     *  execution_allowed_channels:array<int,string>,
     *  execution_policy_notes:?string
     * }
     */
    public function normalizeForSave(
        string $lifecycleStatus,
        string $subscriptionStatus,
        array $entitlements,
        string $paymentExecutionMode,
        ?string $provider,
        array|string|null $allowedChannels,
        mixed $maxTransaction,
        mixed $dailyCap,
        mixed $monthlyCap,
        mixed $makerCheckerThreshold,
        ?string $policyNotes,
        ?int $companyId = null,
    ): array {
        $mode = trim($paymentExecutionMode) !== '' ? trim($paymentExecutionMode) : self::MODE_DECISION_ONLY;
        if (! in_array($mode, $this->supportedModes(), true)) {
            $mode = self::MODE_DECISION_ONLY;
        }

        $normalizedProvider = $this->nullableString($provider);
        $channels = $this->normalizeChannels($allowedChannels);
        $maxAmount = $this->nullableDecimal($maxTransaction);
        $dailyAmount = $this->nullableDecimal($dailyCap);
        $monthlyAmount = $this->nullableDecimal($monthlyCap);
        $checkerAmount = $this->nullableDecimal($makerCheckerThreshold);
        $notes = $this->nullableString($policyNotes);

        $errors = [];

        if ($mode === self::MODE_EXECUTION_ENABLED) {
            if ($lifecycleStatus !== 'active') {
                $errors['subscriptionForm.payment_execution_mode'] = 'Execution-enabled requires tenant lifecycle to be active.';
            }

            if ($subscriptionStatus !== 'current') {
                $errors['subscriptionForm.payment_execution_mode'] = 'Execution-enabled requires billing status to be current.';
            }

            if (! ((bool) ($entitlements['requests_enabled'] ?? false) && (bool) ($entitlements['expenses_enabled'] ?? false))) {
                $errors['subscriptionForm.payment_execution_mode'] = 'Execution-enabled requires Requests and Expenses modules enabled.';
            }

            if ($normalizedProvider === null) {
                $errors['subscriptionForm.execution_provider'] = 'Execution provider is required when mode is execution-enabled.';
            }

            if ($channels === []) {
                $errors['subscriptionForm.execution_allowed_channels'] = 'No execution channel is configured. Go to Tenant Execution Policy and enable at least one channel.';
            }

            if ($maxAmount !== null && $checkerAmount !== null && $checkerAmount > $maxAmount) {
                $errors['subscriptionForm.execution_maker_checker_threshold_amount'] = 'Checker threshold cannot exceed max transaction amount.';
            }

            if ($companyId === null) {
                $errors['subscriptionForm.payment_execution_mode'] = 'Save tenant first, then configure execution-enabled mode.';
            } elseif (! $this->paymentAuthorizationWorkflowResolver->hasActiveDefaultWorkflow($companyId)) {
                // Execution-mode requires a second policy layer after request approval, after the fiinal approval from the request approval workflow.
                $errors['subscriptionForm.payment_execution_mode'] = 'Execution-enabled requires an active default Payment Authorization workflow in Approval Workflows.';
            }
        }

        if ($mode === self::MODE_DECISION_ONLY) {
            // Preserve configured execution policy while mode is disabled.
            // This lets operators pre-configure channels/caps and switch to execution_enabled later without re-entry.
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'payment_execution_mode' => $mode,
            'execution_provider' => $normalizedProvider,
            'execution_max_transaction_amount' => $maxAmount,
            'execution_daily_cap_amount' => $dailyAmount,
            'execution_monthly_cap_amount' => $monthlyAmount,
            'execution_maker_checker_threshold_amount' => $checkerAmount,
            'execution_allowed_channels' => $channels,
            'execution_policy_notes' => $notes,
        ];
    }

    /**
     * @param  array<int, string>|string|null  $value
     * @return array<int, string>
     */
    private function normalizeChannels(array|string|null $value): array
    {
        $list = is_array($value)
            ? $value
            : (is_string($value) && trim($value) !== '' ? [trim($value)] : []);

        $supported = $this->supportedChannels();

        $channels = array_values(array_unique(array_filter(array_map(
            static fn (mixed $channel): string => trim((string) $channel),
            $list
        ), static fn (string $channel): bool => $channel !== '' && in_array($channel, $supported, true))));

        return $channels;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $number = (float) $trimmed;

        return $number > 0 ? $number : null;
    }

    private function nullableString(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}


