<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Procurement\Models\CompanyProcurementControlSetting;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardShell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RoleSpecificDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_lens_scopes_stale_execution_queue_to_current_tenant(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Dashboard Finance Tenant A');
        [$companyB, $departmentB] = $this->createCompanyContext('Dashboard Finance Tenant B');

        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);

        $subscriptionA = $this->createSubscription($companyA, $financeA);
        $subscriptionB = $this->createSubscription($companyB, $financeB);

        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $companyA->id,
            'tenant_subscription_id' => $subscriptionA->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'dashboard-finance-a-billing-001',
            'attempt_status' => 'queued',
            'amount' => 1500,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now()->subMinutes(90),
            'attempt_count' => 1,
        ]);

        // This second-tenant queued row must not inflate tenant A dashboard queue risk.
        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $companyB->id,
            'tenant_subscription_id' => $subscriptionB->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'dashboard-finance-b-billing-001',
            'attempt_status' => 'queued',
            'amount' => 1700,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now()->subMinutes(90),
            'attempt_count' => 1,
        ]);

        $this->actingAs($financeA);

        Livewire::test(DashboardShell::class)
            ->call('loadMetrics')
            ->assertSet('roleView', 'finance')
            ->assertSet('roleTitle', 'Finance Command Center')
            ->assertSet('roleSummaryCards.2.label', 'Stale Execution Queue')
            ->assertSet('roleSummaryCards.2.value', '1')
            ->assertSee('Finance Command Center');
    }

    public function test_owner_lens_highlights_stale_commitments_for_current_tenant(): void
    {
        [$company, $department] = $this->createCompanyContext('Dashboard Owner Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        CompanyProcurementControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => [
                'stale_commitment_alert_age_hours' => 24,
                'stale_commitment_alert_count_threshold' => 1,
            ],
        ]);

        ProcurementCommitment::query()->create([
            'company_id' => $company->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => 92000,
            'currency_code' => 'NGN',
            'effective_at' => now()->subHours(30),
        ]);

        $this->actingAs($owner);

        Livewire::test(DashboardShell::class)
            ->call('loadMetrics')
            ->assertSet('roleView', 'owner')
            ->assertSet('roleTitle', 'Owner Control Tower')
            ->assertSet('roleSummaryCards.1.label', 'Stale Commitments')
            ->assertSet('roleSummaryCards.1.value', '1')
            ->assertSee('Owner Control Tower');
    }

    public function test_auditor_recent_signals_are_filtered_by_tenant(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Dashboard Auditor Tenant A');
        [$companyB, $departmentB] = $this->createCompanyContext('Dashboard Auditor Tenant B');

        $auditorA = $this->createUser($companyA, $departmentA, UserRole::Auditor->value);
        $this->createUser($companyB, $departmentB, UserRole::Auditor->value);

        TenantAuditEvent::query()->create([
            'company_id' => $companyA->id,
            'action' => 'tenant.procurement.match.exception.waived',
            'description' => 'Tenant A waived mismatch evidence',
            'event_at' => now()->subMinutes(4),
        ]);

        TenantAuditEvent::query()->create([
            'company_id' => $companyB->id,
            'action' => 'tenant.procurement.match.exception.waived',
            'description' => 'Tenant B waived mismatch evidence',
            'event_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($auditorA);

        Livewire::test(DashboardShell::class)
            ->call('loadMetrics')
            ->assertSet('roleView', 'auditor')
            ->assertSet('roleTitle', 'Audit & Assurance Lens')
            ->assertSee('Tenant A waived mismatch evidence')
            ->assertDontSee('Tenant B waived mismatch evidence');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+dashboard@example.test',
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

    private function createUser(Company $company, Department $department, string $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
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
}
