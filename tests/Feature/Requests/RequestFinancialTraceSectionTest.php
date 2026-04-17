<?php

namespace Tests\Feature\Requests;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RequestFinancialTraceSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_detail_modal_shows_financial_trace_section(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Trace UI');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $budget = DepartmentBudget::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_amount' => 1000000,
            'used_amount' => 150000,
            'remaining_amount' => 850000,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-TRACE-UI-001',
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'title' => 'Trace UI request',
            'amount' => 250000,
            'approved_amount' => 250000,
            'paid_amount' => 250000,
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
                        'spent_amount' => 150000,
                        'projected_amount' => 400000,
                        'remaining_amount' => 850000,
                        'over_amount' => 0,
                        'is_exceeded' => false,
                        'mode' => 'warn',
                    ],
                ],
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        RequestApproval::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'scope' => 'request',
            'step_order' => 1,
            'step_key' => 'finance_review',
            'status' => 'approved',
            'action' => 'approved',
            'acted_by' => $owner->id,
            'acted_at' => now()->subDays(2),
            'from_status' => 'in_review',
            'to_status' => 'approved_for_execution',
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':trace-ui',
            'execution_status' => 'settled',
            'amount' => 250000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAY-TRACE-UI-001',
            'queued_at' => now()->subDay(),
            'processed_at' => now()->subHours(20),
            'settled_at' => now()->subHours(18),
            'attempt_count' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->call('openViewModal', (int) $request->id)
            ->assertSee('Financial Trace')
            ->assertSee('Budget Check')
            ->assertSee('Within Budget')
            ->assertSee('Payment')
            ->assertSee('Settled')
            ->assertSee('Bank Match')
            ->assertSee('Payment attempt')
            ->assertSee('Payment is settled, but no linked expense is recorded yet.');
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+trace-ui@example.test',
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
