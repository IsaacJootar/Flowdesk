<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Execution\PayoutReadyQueuePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TenantPayoutReadyQueuePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_tenant_user_can_view_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Payout Queue Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('execution.payout-ready'))
            ->assertOk()
            ->assertSee('Payments Ready to Send');
    }

    public function test_staff_and_platform_operator_cannot_view_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Payout Queue Forbidden Tenant');

        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('execution.payout-ready'))
            ->assertForbidden();

        $platformUser = $this->createUser($company, $department, UserRole::Owner->value, PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('execution.payout-ready'))
            ->assertForbidden();
    }

    public function test_queue_rows_are_tenant_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Queue Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Queue Scope B');

        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);

        $requesterA = $this->createUser($companyA, $departmentA, UserRole::Staff->value);
        $requesterB = $this->createUser($companyB, $departmentB, UserRole::Staff->value);

        SpendRequest::query()->create([
            'company_id' => $companyA->id,
            'request_code' => 'FD-QUEUE-A-001',
            'requested_by' => $requesterA->id,
            'department_id' => $departmentA->id,
            'title' => 'Tenant A payout request',
            'amount' => 100000,
            'currency' => 'NGN',
            'status' => 'approved_for_execution',
            'approved_amount' => 100000,
            'created_by' => $financeA->id,
            'updated_by' => $financeA->id,
        ]);

        SpendRequest::query()->create([
            'company_id' => $companyB->id,
            'request_code' => 'FD-QUEUE-B-001',
            'requested_by' => $requesterB->id,
            'department_id' => $departmentB->id,
            'title' => 'Tenant B payout request',
            'amount' => 180000,
            'currency' => 'NGN',
            'status' => 'approved_for_execution',
            'approved_amount' => 180000,
            'created_by' => $financeB->id,
            'updated_by' => $financeB->id,
        ]);

        $this->actingAs($financeA);

        Livewire::test(PayoutReadyQueuePage::class)
            ->call('loadData')
            ->assertSee('FD-QUEUE-A-001')
            ->assertDontSee('FD-QUEUE-B-001');
    }

    public function test_finance_can_run_payout_now_for_ready_request(): void
    {
        [$company, $department] = $this->createCompanyContext('Queue Manual Run Tenant');

        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $requester = $this->createUser($company, $department, UserRole::Staff->value);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-QUEUE-RUN-001',
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'title' => 'Manual run request',
            'amount' => 220000,
            'currency' => 'NGN',
            'status' => 'approved_for_execution',
            'approved_amount' => 220000,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $this->actingAs($finance);

        Livewire::test(PayoutReadyQueuePage::class)
            ->call('loadData')
            ->call('runPayoutNow', (int) $request->id)
            ->assertSet('feedbackError', null)
            ->assertSee('Outcome: skipped');

        $attempt = RequestPayoutExecutionAttempt::query()->where('request_id', (int) $request->id)->first();

        $this->assertNotNull($attempt);
        $this->assertSame('skipped', (string) $attempt->execution_status);

        $request->refresh();
        $this->assertSame('approved_for_execution', (string) $request->status);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.execution.payout.manual_queue_run',
            'entity_type' => SpendRequest::class,
            'entity_id' => (int) $request->id,
        ]);
    }

    public function test_auditor_can_view_queue_but_cannot_run_manual_payout_action(): void
    {
        [$company, $department] = $this->createCompanyContext('Queue Auditor Guardrail Tenant');

        $auditor = $this->createUser($company, $department, UserRole::Auditor->value);
        $requester = $this->createUser($company, $department, UserRole::Staff->value);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
            'created_by' => $auditor->id,
            'updated_by' => $auditor->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-QUEUE-AUD-001',
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'title' => 'Auditor guardrail request',
            'amount' => 115000,
            'currency' => 'NGN',
            'status' => 'approved_for_execution',
            'approved_amount' => 115000,
            'created_by' => $auditor->id,
            'updated_by' => $auditor->id,
        ]);

        $this->actingAs($auditor);

        Livewire::test(PayoutReadyQueuePage::class)
            ->call('loadData')
            ->assertViewHas('canRunPayoutActions', false);

        Livewire::test(PayoutReadyQueuePage::class)
            ->call('runPayoutNow', (int) $request->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('request_payout_execution_attempts', [
            'company_id' => $company->id,
            'request_id' => (int) $request->id,
        ]);
    }

    public function test_flow_agent_can_analyze_payout_risk_when_ai_is_enabled_for_tenant(): void
    {
        [$company, $department] = $this->createCompanyContext('Queue Flow Agent Tenant');

        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $requester = $this->createUser($company, $department, UserRole::Staff->value);
        $this->enableAiForCompany($company, $finance);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-QUEUE-RISK-001',
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'title' => 'High value payout request',
            'amount' => 1600000,
            'currency' => 'NGN',
            'status' => 'approved_for_execution',
            'approved_amount' => 1600000,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => (int) $request->id,
            'tenant_subscription_id' => (int) $subscription->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':risk-test-001',
            'execution_status' => 'failed',
            'amount' => 1600000,
            'currency_code' => 'NGN',
            'queued_at' => now()->subHours(3),
            'failed_at' => now()->subHours(2),
            'attempt_count' => 2,
            'error_code' => 'provider_timeout',
            'error_message' => 'Simulated timeout',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $this->actingAs($finance);

        Livewire::test(PayoutReadyQueuePage::class)
            ->call('loadData')
            ->call('analyzePayoutRisk', (int) $request->id)
            ->assertSet('feedbackError', null)
            ->assertSee('Use Flow Agent')
            ->assertSee('risk');

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.execution.payout.risk_analyzed',
            'entity_type' => SpendRequest::class,
            'entity_id' => (int) $request->id,
        ]);
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+payout-queue@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Finance',
            'code' => 'FIN',
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

    private function enableAiForCompany(Company $company, User $actor): void
    {
        TenantFeatureEntitlement::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'ai_enabled' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }
}
