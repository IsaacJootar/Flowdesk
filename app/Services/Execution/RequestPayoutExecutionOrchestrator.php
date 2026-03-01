<?php

namespace App\Services\Execution;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Jobs\Execution\RunRequestPayoutExecutionAttemptJob;
use App\Services\TenantExecutionModeService;

class RequestPayoutExecutionOrchestrator
{
    public function queueForApprovedRequest(SpendRequest $request, ?int $actorUserId = null): ?RequestPayoutExecutionAttempt
    {
        $request->loadMissing(['company.subscription']);

        $subscription = $request->company?->subscription;
        if (! $subscription || (string) $subscription->payment_execution_mode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED) {
            return null;
        }

        $provider = trim((string) ($subscription->execution_provider ?? ''));
        if ($provider === '') {
            return null;
        }

        $amount = (int) ($request->approved_amount ?: $request->amount);
        if ($amount < 1) {
            return null;
        }

        $allowedChannels = array_values(array_filter(array_map('strval', (array) ($subscription->execution_allowed_channels ?? []))));
        $channel = $allowedChannels[0] ?? 'bank_transfer';

        $attempt = RequestPayoutExecutionAttempt::query()->firstOrCreate(
            ['request_id' => (int) $request->id],
            [
                'company_id' => (int) $request->company_id,
                'tenant_subscription_id' => (int) $subscription->id,
                'provider_key' => strtolower($provider),
                'execution_channel' => $channel,
                'idempotency_key' => sprintf('request:%d:payout', (int) $request->id),
                'execution_status' => 'queued',
                'amount' => (float) $amount,
                'currency_code' => strtoupper((string) ($request->currency ?: $request->company?->currency_code ?: config('execution.billing.default_currency', 'NGN'))),
                'queued_at' => now(),
                'attempt_count' => 1,
                'metadata' => [
                    'request_code' => (string) $request->request_code,
                ],
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]
        );

        if ($attempt->wasRecentlyCreated) {
            // Request status moves to execution queue once payout attempt is created.
            $request->forceFill([
                'status' => 'execution_queued',
                'updated_by' => $actorUserId,
                'metadata' => array_merge((array) ($request->metadata ?? []), [
                    'execution' => [
                        'payout_attempt_id' => (int) $attempt->id,
                        'queued_at' => now()->toDateTimeString(),
                    ],
                ]),
            ])->save();

            RunRequestPayoutExecutionAttemptJob::dispatch((int) $attempt->id);
        }

        return $attempt;
    }
}