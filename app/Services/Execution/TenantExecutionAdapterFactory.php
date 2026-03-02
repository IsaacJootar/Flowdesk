<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\TenantSubscription;
use App\Services\Execution\Contracts\PayoutExecutionAdapterInterface;
use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\Contracts\SubscriptionBillingAdapterInterface;
use App\Services\TenantExecutionModeService;

/**
 * Tenant-aware factory that selects adapters from execution mode + provider state.
 */
class TenantExecutionAdapterFactory
{
    public function __construct(
        private readonly ExecutionAdapterRegistry $registry,
    ) {
    }

    public function billingAdapterForSubscription(?TenantSubscription $subscription): SubscriptionBillingAdapterInterface
    {
        return $this->registry->resolveSubscriptionBillingAdapter(
            $this->providerForSubscription($subscription)
        );
    }

    public function payoutAdapterForSubscription(?TenantSubscription $subscription): PayoutExecutionAdapterInterface
    {
        return $this->registry->resolvePayoutExecutionAdapter(
            $this->providerForSubscription($subscription)
        );
    }

    public function webhookVerifierForProvider(?string $providerKey): ProviderWebhookVerifierInterface
    {
        return $this->registry->resolveWebhookVerifier($providerKey);
    }

    private function providerForSubscription(?TenantSubscription $subscription): ?string
    {
        if (! $subscription) {
            return null;
        }

        $mode = (string) ($subscription->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY); // if execution mode is not set, default to decision-only for safety.
        if ($mode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED) {
            // Decision-only tenants are always forced through the null adapter.
            return null;
        }

        $provider = trim((string) ($subscription->execution_provider ?? ''));

        return $provider !== '' ? $provider : null;
    }
}
