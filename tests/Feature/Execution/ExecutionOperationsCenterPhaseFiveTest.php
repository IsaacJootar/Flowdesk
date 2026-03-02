<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Platform\ExecutionOperationsPage;
use App\Models\User;
use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Fakes\Execution\FakeSubscriptionBillingAdapter;
use Tests\Fakes\Execution\FakeWebhookVerifier;
use Tests\TestCase;
class ExecutionOperationsCenterPhaseFiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_view_operations_center(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('platform.operations.execution'))
            ->assertOk()
            ->assertSee('Execution Operations Center')
            ->assertSee('Failure Rate')
            ->assertSee('Runbook Hints')
            ->assertSee('Auto Recovery Runs')
            ->assertSee('Alert Summaries');
    }

    public function test_non_platform_user_cannot_view_operations_center(): void
    {
        $tenant = $this->createTenantCompany('Ops Center Access Tenant');
        $user = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Owner->value,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('platform.operations.execution'))
            ->assertForbidden();
    }

    public function test_retry_failed_billing_attempt_processes_attempt_and_writes_audit_event(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);

        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOwner->value);
        $tenant = $this->createTenantCompany('Ops Retry Billing Tenant');

        $subscription = TenantSubscription::query()->create([
            'company_id' => $tenant->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'fake_provider',
            'created_by' => $platformUser->id,
            'updated_by' => $platformUser->id,
        ]);

        $attempt = TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'fake_provider',
            'billing_cycle_key' => now()->format('Y-m'),
            'idempotency_key' => 'tenant:'.$tenant->id.':billing:retry:001',
            'attempt_status' => 'failed',
            'amount' => 18000,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'failed_at' => now(),
            'attempt_count' => 1,
            'error_code' => 'provider_error',
            'error_message' => 'Simulated provider failure',
        ]);

        $this->actingAs($platformUser);

        Livewire::test(ExecutionOperationsPage::class)
            ->call('loadData')
            ->set('billingRetryReason', 'Manual retry from operations center')
            ->call('retryBillingAttempt', (int) $attempt->id)
            ->assertSet('feedbackError', null);

        $attempt->refresh();

        $this->assertContains((string) $attempt->attempt_status, ['webhook_pending', 'settled', 'failed', 'skipped']);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $tenant->id,
            'action' => 'tenant.execution.billing.retry_requested',
            'entity_type' => TenantSubscriptionBillingAttempt::class,
            'entity_id' => $attempt->id,
        ]);
    }


    public function test_platform_recovery_processes_queued_payout_when_request_exists_but_actor_is_platform_scoped(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenant = $this->createTenantCompany('Ops Payout Scope Tenant');

        $requester = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Staff->value,
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $tenant->id,
            'name' => 'Finance',
            'code' => 'FIN',
            'is_active' => true,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $tenant->id,
            'request_code' => 'FD-REQ-SCOPE-0001',
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'title' => 'Scope-safe payout request',
            'amount' => 150000,
            'currency' => 'NGN',
            'status' => 'execution_queued',
            'approved_amount' => 150000,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $tenant->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
            'created_by' => $platformUser->id,
            'updated_by' => $platformUser->id,
        ]);

        $attempt = RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $tenant->id,
            'request_id' => $request->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':scope-test',
            'execution_status' => 'queued',
            'amount' => 150000,
            'currency_code' => 'NGN',
            'queued_at' => now()->subMinutes(45),
            'attempt_count' => 1,
            'created_by' => $platformUser->id,
            'updated_by' => $platformUser->id,
        ]);

        $this->actingAs($platformUser);

        Livewire::test(ExecutionOperationsPage::class)
            ->call('loadData')
            ->set('batchReason', 'Run payout recovery under platform scope')
            ->set('batchOlderThanMinutes', '30')
            ->call('processStuckPayoutQueued')
            ->assertSet('feedbackError', null)
            ->assertSet('feedbackMessage', 'Processed 1 of 1 queued payout attempts older than 30 mins. 1 ended as skipped (no-op provider).');

        $attempt->refresh();
        $request->refresh();

        $this->assertSame('skipped', (string) $attempt->execution_status);
        $this->assertSame('approved_for_execution', (string) $request->status);
    }

    public function test_manual_reconcile_webhook_updates_linked_attempt_and_event(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenant = $this->createTenantCompany('Ops Manual Reconcile Tenant');

        $subscription = TenantSubscription::query()->create([
            'company_id' => $tenant->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'created_by' => $platformUser->id,
            'updated_by' => $platformUser->id,
        ]);

        $attempt = TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => now()->format('Y-m'),
            'idempotency_key' => 'tenant:'.$tenant->id.':billing:manual-reconcile:001',
            'attempt_status' => 'webhook_pending',
            'amount' => 22000,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now(),
            'attempt_count' => 1,
        ]);

        $event = ExecutionWebhookEvent::query()->create([
            'provider_key' => 'manual_ops',
            'external_event_id' => 'evt-manual-reconcile-001',
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'tenant_subscription_billing_attempt_id' => $attempt->id,
            'event_type' => 'payment.settled',
            'verification_status' => 'valid',
            'processing_status' => 'queued',
            'received_at' => now(),
            'normalized_payload' => [
                'billing_attempt_id' => (int) $attempt->id,
                'status' => 'settled',
            ],
        ]);

        $this->actingAs($platformUser);

        Livewire::test(ExecutionOperationsPage::class)
            ->call('loadData')
            ->set('webhookReconcileReason', 'Provider dashboard confirms settlement')
            ->call('reconcileWebhookEvent', (int) $event->id)
            ->assertSet('feedbackError', null);

        $this->assertDatabaseHas('tenant_subscription_billing_attempts', [
            'id' => $attempt->id,
            'attempt_status' => 'settled',
            'last_provider_event_id' => 'evt-manual-reconcile-001',
        ]);

        $this->assertDatabaseHas('execution_webhook_events', [
            'id' => $event->id,
            'processing_status' => 'processed',
        ]);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $tenant->id,
            'action' => 'tenant.execution.webhook.manual_reconciled_billing',
            'entity_type' => ExecutionWebhookEvent::class,
            'entity_id' => $event->id,
        ]);
    }

    private function createPlatformUser(string $platformRole): User
    {
        return User::factory()->create([
            'company_id' => null,
            'department_id' => null,
            'role' => UserRole::Owner->value,
            'platform_role' => $platformRole,
            'is_active' => true,
        ]);
    }

    private function createTenantCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }
}






