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
    public function resolveSubscriptionBillingAdapter(?string $providerKey): SubscriptionBillingAdapterInterface
    {
        $provider = $this->normalizedProvider($providerKey);
        $class = (string) ($this->providerConfig($provider)['subscription_billing_adapter'] ?? NullSubscriptionBillingAdapter::class);

        return $this->resolve($class, SubscriptionBillingAdapterInterface::class);
    }

    public function resolvePayoutExecutionAdapter(?string $providerKey): PayoutExecutionAdapterInterface
    {
        $provider = $this->normalizedProvider($providerKey);
        $class = (string) ($this->providerConfig($provider)['payout_execution_adapter'] ?? NullPayoutExecutionAdapter::class);

        return $this->resolve($class, PayoutExecutionAdapterInterface::class);
    }

    public function resolveWebhookVerifier(?string $providerKey): ProviderWebhookVerifierInterface
    {
        $provider = $this->normalizedProvider($providerKey);
        $class = (string) ($this->providerConfig($provider)['webhook_verifier'] ?? NullProviderWebhookVerifier::class);

        return $this->resolve($class, ProviderWebhookVerifierInterface::class);
    }

    /**
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

    private function normalizedProvider(?string $providerKey): string
    {
        $trimmed = trim((string) $providerKey);

        return $trimmed !== '' ? strtolower($trimmed) : (string) config('execution.fallback_provider', 'null');
    }

    /**
     * @template T of object
     * @param  class-string<T>  $expectedInterface
     * @return T
     */
    private function resolve(string $class, string $expectedInterface): object
    {
        $instance = app($class);

        if (! $instance instanceof $expectedInterface) {
            throw new InvalidArgumentException(sprintf(
                'Configured adapter [%s] must implement [%s].',
                $class,
                $expectedInterface
            ));
        }

        return $instance;
    }
}
