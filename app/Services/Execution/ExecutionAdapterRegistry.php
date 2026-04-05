<?php

namespace App\Services\Execution;

use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use App\Services\Execution\Adapters\NullProviderWebhookVerifier;
use App\Services\Execution\Adapters\NullSubscriptionBillingAdapter;
use App\Services\Execution\Contracts\PayoutExecutionAdapterInterface;
use App\Services\Execution\Contracts\ProviderWebhookVerifierInterface;
use App\Services\Execution\Contracts\SubscriptionBillingAdapterInterface;
use InvalidArgumentException;

/**
 * Resolves provider adapters from config so orchestration remains provider-agnostic.
 */
class ExecutionAdapterRegistry
{
    /**
     * Resolve the subscription billing adapter for a provider.
     */
    public function resolveSubscriptionBillingAdapter(?string $providerKey): SubscriptionBillingAdapterInterface
    {
        $provider = $this->normalizedProvider($providerKey);
        $class = (string) ($this->providerConfig($provider)['subscription_billing_adapter'] ?? NullSubscriptionBillingAdapter::class);

        $instance = app($class);
        if (! $instance instanceof SubscriptionBillingAdapterInterface) {
            throw new InvalidArgumentException(sprintf(
                'Configured adapter [%s] must implement [%s].',
                $class,
                SubscriptionBillingAdapterInterface::class
            ));
        }

        return $instance;
    }

    /**
     * Resolve the payout execution adapter for a provider.
     */
    public function resolvePayoutExecutionAdapter(?string $providerKey): PayoutExecutionAdapterInterface
    {
        $provider = $this->normalizedProvider($providerKey);
        $class = (string) ($this->providerConfig($provider)['payout_execution_adapter'] ?? NullPayoutExecutionAdapter::class);

        $instance = app($class);
        if (! $instance instanceof PayoutExecutionAdapterInterface) {
            throw new InvalidArgumentException(sprintf(
                'Configured adapter [%s] must implement [%s].',
                $class,
                PayoutExecutionAdapterInterface::class
            ));
        }

        return $instance;
    }

    /**
     * Resolve the webhook verifier for a provider.
     */
    public function resolveWebhookVerifier(?string $providerKey): ProviderWebhookVerifierInterface
    {
        $provider = $this->normalizedProvider($providerKey);
        $class = (string) ($this->providerConfig($provider)['webhook_verifier'] ?? NullProviderWebhookVerifier::class);

        $instance = app($class);
        if (! $instance instanceof ProviderWebhookVerifierInterface) {
            throw new InvalidArgumentException(sprintf(
                'Configured adapter [%s] must implement [%s].',
                $class,
                ProviderWebhookVerifierInterface::class
            ));
        }

        return $instance;
    }

    /**
     * Get all configured provider keys.
     *
     * @return array<int, string>
     */
    public function providerKeys(): array
    {
        $providers = array_keys((array) config('execution.providers', []));

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $provider): string => trim((string) $provider),
            $providers
        ))));
    }

    /**
     * Get the config for a provider.
     *
     * @return array<string,mixed>
     */
    private function providerConfig(string $provider): array
    {
        $providers = (array) config('execution.providers', []);

        if (isset($providers[$provider]) && is_array($providers[$provider])) {
            return (array) $providers[$provider];
        }

        return (array) ($providers[(string) config('execution.fallback_provider', 'null')] ?? []);
    }

    /**
     * Normalize the provider key.
     */
    private function normalizedProvider(?string $providerKey): string
    {
        $trimmed = trim((string) $providerKey);

        return $trimmed !== '' ? strtolower($trimmed) : (string) config('execution.fallback_provider', 'null');
    }
}
