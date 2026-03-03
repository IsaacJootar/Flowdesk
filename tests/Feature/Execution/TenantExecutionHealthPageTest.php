<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Execution\ExecutionHealthPage;
use App\Models\User;
use App\Services\NavAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TenantExecutionHealthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_tenant_user_can_view_execution_health_page_and_nav_item(): void
    {
        [$company, $department] = $this->createCompanyContext('Health Access Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('execution.health'))
            ->assertOk()
            ->assertSee('Execution Health');

        $routes = array_column(app(NavAccessService::class)->forUser($owner)['items'], 'route');
        $this->assertContains('execution.health', $routes);
    }

    public function test_staff_and_platform_users_cannot_view_tenant_execution_health_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Health Forbidden Tenant');

        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('execution.health'))
            ->assertForbidden();

        $platformUser = $this->createUser($company, $department, UserRole::Owner->value, PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('execution.health'))
            ->assertForbidden();
    }

    public function test_action_needed_status_shows_support_incident_id_from_recent_alert(): void
    {
        [$company, $department] = $this->createCompanyContext('Health Alert Tenant');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $subscription = $this->createSubscription($company, $finance);
        $request = $this->createRequest($company, $department, $finance, 1200, 'FD-HLTH-ACT-001');

        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $company->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'tenant-health-billing-001',
            'attempt_status' => 'failed',
            'amount' => 1000,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'attempt_count' => 1,
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'tenant-health-payout-001',
            'execution_status' => 'failed',
            'amount' => 1200,
            'currency_code' => 'NGN',
            'attempt_count' => 1,
        ]);

        $alert = TenantAuditEvent::query()->create([
            'company_id' => $company->id,
            'action' => 'tenant.execution.alert.summary_emitted',
            'description' => 'Execution alert threshold breached during ops summary run.',
            'metadata' => [
                'pipeline' => 'payout',
                'type' => 'stuck_queued',
            ],
            'event_at' => now()->subMinutes(3),
        ]);

        $expectedIncident = 'EXE-'.str_pad((string) $alert->id, 6, '0', STR_PAD_LEFT);

        $this->actingAs($finance);

        Livewire::test(ExecutionHealthPage::class)
            ->call('loadData')
            ->assertSet('summary.status_label', 'Action needed')
            ->assertSet('summary.affected_billings', 1)
            ->assertSet('summary.affected_payouts', 1)
            ->assertSee('Action needed')
            ->assertSee('Contact support with incident ID')
            ->assertSee($expectedIncident);
    }

    public function test_recent_procurement_alert_is_visible_in_tenant_execution_health_summaries(): void
    {
        [$company, $department] = $this->createCompanyContext('Health Procurement Alert Tenant');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        TenantAuditEvent::query()->create([
            'company_id' => $company->id,
            'action' => 'tenant.execution.alert.summary_emitted',
            'description' => 'Execution alert threshold breached during ops summary run.',
            'metadata' => [
                'pipeline' => 'procurement',
                'type' => 'stale_commitment',
                'count' => 4,
                'threshold' => 3,
                'age_hours' => 72,
            ],
            'event_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($finance);

        Livewire::test(ExecutionHealthPage::class)
            ->call('loadData')
            ->assertSet('summary.status_label', 'Action needed')
            ->assertSee('Procurement pipeline requires attention.');
    }

    public function test_delayed_status_is_scoped_to_current_tenant_counts(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Health Tenant A');
        [$companyB, $departmentB] = $this->createCompanyContext('Health Tenant B');

        $manager = $this->createUser($companyA, $departmentA, UserRole::Manager->value);
        $managerB = $this->createUser($companyB, $departmentB, UserRole::Manager->value);

        $subscriptionA = $this->createSubscription($companyA, $manager);
        $subscriptionB = $this->createSubscription($companyB, $managerB);

        $requestA = $this->createRequest($companyA, $departmentA, $manager, 2200, 'FD-HLTH-A-001');
        $requestB = $this->createRequest($companyB, $departmentB, $managerB, 3200, 'FD-HLTH-B-001');

        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $companyA->id,
            'tenant_subscription_id' => $subscriptionA->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'tenant-a-billing-001',
            'attempt_status' => 'failed',
            'amount' => 2000,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'attempt_count' => 1,
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $companyA->id,
            'request_id' => $requestA->id,
            'tenant_subscription_id' => $subscriptionA->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'tenant-a-payout-001',
            'execution_status' => 'queued',
            'amount' => 2200,
            'currency_code' => 'NGN',
            'queued_at' => now()->subMinutes(90),
            'attempt_count' => 1,
        ]);

        // Other tenant rows must never appear in tenant A counts.
        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $companyB->id,
            'tenant_subscription_id' => $subscriptionB->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'tenant-b-billing-001',
            'attempt_status' => 'failed',
            'amount' => 3100,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'attempt_count' => 1,
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $companyB->id,
            'request_id' => $requestB->id,
            'tenant_subscription_id' => $subscriptionB->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'tenant-b-payout-001',
            'execution_status' => 'queued',
            'amount' => 3200,
            'currency_code' => 'NGN',
            'queued_at' => now()->subMinutes(90),
            'attempt_count' => 1,
        ]);

        $this->actingAs($manager);

        Livewire::test(ExecutionHealthPage::class)
            ->call('loadData')
            ->assertSet('summary.status_label', 'Delayed')
            ->assertSet('summary.affected_billings', 1)
            ->assertSet('summary.affected_payouts', 1)
            ->assertSet('summary.next_action', 'Retry later.');
    }
    public function test_execution_health_can_show_focused_billing_context_from_deep_link(): void
    {
        [$company, $department] = $this->createCompanyContext('Health Focus Tenant');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $subscription = $this->createSubscription($company, $finance);

        $attempt = TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $company->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'tenant-focus-billing-001',
            'attempt_status' => 'failed',
            'amount' => 1800,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'attempt_count' => 1,
        ]);

        $this->actingAs($finance);

        Livewire::test(ExecutionHealthPage::class)
            ->set('focusRequested', true)
            ->set('focusPipeline', 'billing')
            ->set('focusBillingAttemptId', (int) $attempt->id)
            ->set('focusIncidentId', 'EXE-123456')
            ->call('loadData')
            ->assertSet('focusContext.pipeline', 'Billing')
            ->assertSet('focusContext.record_label', 'Billing attempt #'.(int) $attempt->id)
            ->assertSee('Focused Execution Context')
            ->assertSee('Billing attempt #'.(int) $attempt->id)
            ->assertSee('EXE-123456');
    }
    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+health@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        return [$company, $department];
    }

    private function createUser(Company $company, Department $department, string $role, ?string $platformRole = null): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'platform_role' => $platformRole,
            'is_active' => true,
        ]);
    }

    private function createSubscription(Company $company, User $actor): TenantSubscription
    {
        return TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function createRequest(Company $company, Department $department, User $requester, int $amount, string $code): SpendRequest
    {
        return SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => $code,
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'title' => 'Tenant execution health test request',
            'amount' => $amount,
            'currency' => 'NGN',
            'status' => 'execution_queued',
            'approved_amount' => $amount,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);
    }
}

