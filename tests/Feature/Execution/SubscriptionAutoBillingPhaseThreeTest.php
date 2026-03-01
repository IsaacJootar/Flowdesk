<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use App\Services\Execution\SubscriptionAutoBillingOrchestrator;
use App\Services\Execution\SubscriptionBillingAttemptProcessor;
use App\Services\Execution\SubscriptionBillingWebhookReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Fakes\Execution\FakeSubscriptionBillingAdapter;
use Tests\Fakes\Execution\FakeWebhookVerifier;
use Tests\TestCase;

class SubscriptionAutoBillingPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_queues_monthly_attempt_for_execution_enabled_subscription(): void
    {
        config()->set('execution.billing.plan_amounts.growth', 15000);

        $company = $this->createExternalTenantCompany('Phase3 Queue Tenant');
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
        ]);

        $stats = app(SubscriptionAutoBillingOrchestrator::class)->dispatchDueBilling(
            companyId: (int) $company->id,
            actor: null,
            queueJobs: false,
        );

        $this->assertSame(1, $stats['scanned']);
        $this->assertSame(1, $stats['queued']);
        $this->assertDatabaseHas('tenant_subscription_billing_attempts', [
            'company_id' => $company->id,
            'attempt_status' => 'queued',
            'amount' => 15000.00,
            'provider_key' => 'manual_ops',
        ]);
    }

    public function test_processor_moves_attempt_to_webhook_pending_with_provider_response(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);
        config()->set('execution.billing.plan_amounts.growth', 22000);

        $company = $this->createExternalTenantCompany('Phase3 Process Tenant');
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'fake_provider',
        ]);

        app(SubscriptionAutoBillingOrchestrator::class)->dispatchDueBilling(
            companyId: (int) $company->id,
            queueJobs: false,
        );

        $attempt = TenantSubscriptionBillingAttempt::query()->firstOrFail();

        $processed = app(SubscriptionBillingAttemptProcessor::class)->processAttemptById((int) $attempt->id);

        $this->assertTrue($processed);
        $this->assertDatabaseHas('tenant_subscription_billing_attempts', [
            'id' => $attempt->id,
            'attempt_status' => 'webhook_pending',
            'external_invoice_id' => 'inv-fake-001',
            'provider_reference' => 'fake-billing-ref',
        ]);
    }

    public function test_webhook_reconciliation_marks_attempt_settled_and_posts_ledger_debit(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);

        $company = $this->createExternalTenantCompany('Phase3 Webhook Tenant');
        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'fake_provider',
        ]);

        $attempt = TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $company->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'fake_provider',
            'billing_cycle_key' => now()->format('Y-m'),
            'idempotency_key' => 'tenant:'.$company->id.':subscription:'.$subscription->id.':cycle:'.now()->format('Y-m'),
            'attempt_status' => 'webhook_pending',
            'amount' => 17500,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now(),
            'attempt_count' => 1,
        ]);

        $payload = json_encode([
            'event_id' => 'evt-phase3-001',
            'event_type' => 'payment.settled',
            'billing_attempt_id' => (int) $attempt->id,
            'idempotency_key' => (string) $attempt->idempotency_key,
            'status' => 'settled',
        ], JSON_THROW_ON_ERROR);

        $result = app(SubscriptionBillingWebhookReconciliationService::class)->receive(
            provider: 'fake_provider',
            headers: ['x-test' => '1'],
            body: $payload,
            signature: null,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['status']);

        $this->assertDatabaseHas('tenant_subscription_billing_attempts', [
            'id' => $attempt->id,
            'attempt_status' => 'settled',
            'last_provider_event_id' => 'evt-phase3-001',
        ]);

        $this->assertDatabaseHas('tenant_billing_ledger_entries', [
            'company_id' => $company->id,
            'tenant_subscription_id' => $subscription->id,
            'source_type' => TenantSubscriptionBillingAttempt::class,
            'source_id' => $attempt->id,
            'entry_type' => 'charge',
            'direction' => 'debit',
        ]);

        $this->assertDatabaseHas('execution_webhook_events', [
            'provider_key' => 'fake_provider',
            'external_event_id' => 'evt-phase3-001',
            'processing_status' => 'processed',
            'verification_status' => 'valid',
        ]);
    }

    private function createExternalTenantCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'email' => Str::slug($name).'@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }
}