<?php

namespace Tests\Feature\Reports;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Reports\FinancialTraceReportPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialTraceReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_view_budget_to_payment_trace_report(): void
    {
        [$company, $department] = $this->createCompanyContext('Financial Trace Report');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $budget = DepartmentBudget::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_amount' => 1000000,
            'used_amount' => 200000,
            'remaining_amount' => 800000,
            'status' => 'active',
            'created_by' => $finance->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-TRACE-RPT-001',
            'requested_by' => $finance->id,
            'department_id' => $department->id,
            'title' => 'Trace report request',
            'amount' => 300000,
            'approved_amount' => 300000,
            'paid_amount' => 300000,
            'currency' => 'NGN',
            'status' => 'settled',
            'submitted_at' => now()->subDays(3),
            'decided_at' => now()->subDays(2),
            'metadata' => [
                'policy_checks' => [
                    'budget' => [
                        'has_budget' => true,
                        'budget_id' => (int) $budget->id,
                        'allocated_amount' => 1000000,
                        'spent_amount' => 200000,
                        'projected_amount' => 500000,
                        'remaining_amount' => 800000,
                        'over_amount' => 0,
                        'is_exceeded' => false,
                        'mode' => 'warn',
                    ],
                ],
            ],
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        RequestApproval::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'scope' => 'request',
            'step_order' => 1,
            'step_key' => 'finance_review',
            'status' => 'approved',
            'action' => 'approved',
            'acted_by' => $finance->id,
            'acted_at' => now()->subDays(2),
            'from_status' => 'in_review',
            'to_status' => 'approved_for_execution',
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':trace-report',
            'execution_status' => 'settled',
            'amount' => 300000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAY-TRACE-RPT-001',
            'queued_at' => now()->subDay(),
            'processed_at' => now()->subHours(20),
            'settled_at' => now()->subHours(18),
            'attempt_count' => 1,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $this->actingAs($finance)
            ->get(route('reports.financial-trace'))
            ->assertOk()
            ->assertSee('Budget to Payment Trace');

        Livewire::test(FinancialTraceReportPage::class)
            ->call('loadData')
            ->assertSee('FD-TRACE-RPT-001')
            ->assertSee('Within Budget')
            ->assertSee('1 / 1 approved')
            ->assertSee('Settled')
            ->assertSee('Bank Transfer')
            ->assertSee('PAY-TRACE-RPT-001')
            ->assertSee('Not matched')
            ->assertSee('Payment is settled, but no linked expense is recorded yet.');
    }

    public function test_staff_cannot_view_budget_to_payment_trace_report(): void
    {
        [$company, $department] = $this->createCompanyContext('Financial Trace Report Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('reports.financial-trace'))
            ->assertForbidden();
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+trace-report@example.test',
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

    private function createUser(Company $company, Department $department, string $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
