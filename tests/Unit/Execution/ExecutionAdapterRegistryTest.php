<?php

namespace Tests\Unit\Execution;

use App\Domains\Company\Models\TenantSubscription;
use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use App\Services\Execution\Adapters\NullProviderWebhookVerifier;
use App\Services\Execution\Adapters\NullSubscriptionBillingAdapter;
use App\Services\Execution\ExecutionAdapterRegistry;
use App\Services\Execution\TenantExecutionAdapterFactory;
use App\Services\TenantExecutionModeService;
use Tests\Fakes\Execution\FakePayoutExecutionAdapter;
use Tests\Fakes\Execution\FakeSubscriptionBillingAdapter;
use Tests\Fakes\Execution\FakeWebhookVerifier;
use Tests\TestCase;

class ExecutionAdapterRegistryTest extends TestCase
{
    public function test_registry_falls_back_to_null_adapters_for_unknown_provider(): void
    {
        $registry = app(ExecutionAdapterRegistry::class);

        $this->assertInstanceOf(NullSubscriptionBillingAdapter::class, $registry->resolveSubscriptionBillingAdapter('unknown_provider'));
        $this->assertInstanceOf(NullPayoutExecutionAdapter::class, $registry->resolvePayoutExecutionAdapter('unknown_provider'));
        $this->assertInstanceOf(NullProviderWebhookVerifier::class, $registry->resolveWebhookVerifier('unknown_provider'));
    }

    public function test_registry_resolves_configured_provider_adapters(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => FakePayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);

        $registry = app(ExecutionAdapterRegistry::class);

        $this->assertInstanceOf(FakeSubscriptionBillingAdapter::class, $registry->resolveSubscriptionBillingAdapter('fake_provider'));
        $this->assertInstanceOf(FakePayoutExecutionAdapter::class, $registry->resolvePayoutExecutionAdapter('fake_provider'));
        $this->assertInstanceOf(FakeWebhookVerifier::class, $registry->resolveWebhookVerifier('fake_provider'));
    }

    public function test_tenant_factory_forces_null_adapters_in_decision_only_mode(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => FakePayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);

        $subscription = new TenantSubscription([
            'payment_execution_mode' => TenantExecutionModeService::MODE_DECISION_ONLY,
            'execution_provider' => 'fake_provider',
        ]);

        $factory = app(TenantExecutionAdapterFactory::class);

        $this->assertInstanceOf(NullSubscriptionBillingAdapter::class, $factory->billingAdapterForSubscription($subscription));
        $this->assertInstanceOf(NullPayoutExecutionAdapter::class, $factory->payoutAdapterForSubscription($subscription));
    }

    public function test_tenant_factory_resolves_provider_adapters_when_execution_is_enabled(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => FakePayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);

        $subscription = new TenantSubscription([
            'payment_execution_mode' => TenantExecutionModeService::MODE_EXECUTION_ENABLED,
            'execution_provider' => 'fake_provider',
        ]);

        $factory = app(TenantExecutionAdapterFactory::class);

        $this->assertInstanceOf(FakeSubscriptionBillingAdapter::class, $factory->billingAdapterForSubscription($subscription));
        $this->assertInstanceOf(FakePayoutExecutionAdapter::class, $factory->payoutAdapterForSubscription($subscription));
        $this->assertInstanceOf(FakeWebhookVerifier::class, $factory->webhookVerifierForProvider('fake_provider'));
    }
}
