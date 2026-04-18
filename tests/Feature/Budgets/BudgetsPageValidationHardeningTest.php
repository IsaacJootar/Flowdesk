<?php

namespace Tests\Feature\Budgets;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Budgets\BudgetsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetsPageValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_budgets_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget Filter Harden');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(BudgetsPage::class)
            ->set('departmentFilter', 'invalid')
            ->assertSet('departmentFilter', 'all')
            ->set('statusFilter', 'unexpected')
            ->assertSet('statusFilter', 'all')
            ->set('periodTypeFilter', 'weekly')
            ->assertSet('periodTypeFilter', 'all')
            ->set('perPage', 999)
            ->assertSet('perPage', 10);
    }

    public function test_save_rejects_foreign_department_id(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Budget Save Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Budget Save Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $this->actingAs($ownerA);

        Livewire::test(BudgetsPage::class)
            ->set('form.department_id', (string) $departmentB->id)
            ->set('form.period_type', 'monthly')
            ->set('form.period_start', '2026-03-01')
            ->set('form.period_end', '2026-03-31')
            ->set('form.allocated_amount', '500000')
            ->call('save')
            ->assertHasErrors(['form.department_id']);
    }

    public function test_close_budget_requires_review_reason_and_closes_after_confirmation(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget Close Review');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $budget = $this->createBudget($company, $department, $finance);

        Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'FD-EXP-BUDGET-CLOSE',
            'department_id' => $department->id,
            'title' => 'Posted budget spend',
            'amount' => 125000,
            'expense_date' => '2026-04-10',
            'payment_method' => 'transfer',
            'paid_by_user_id' => $finance->id,
            'created_by' => $finance->id,
            'status' => 'posted',
            'is_direct' => true,
        ]);

        SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-BUDGET-CLOSE',
            'requested_by' => $finance->id,
            'department_id' => $department->id,
            'title' => 'Open request before close',
            'amount' => 200000,
            'approved_amount' => 200000,
            'currency' => 'NGN',
            'status' => 'in_review',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $this->actingAs($finance);

        Livewire::test(BudgetsPage::class)
            ->call('openCloseModal', (int) $budget->id)
            ->assertSet('showCloseModal', true)
            ->assertSee('Budget Close Review')
            ->assertSee('1 request(s)')
            ->call('submitCloseBudget')
            ->assertHasErrors(['closeReason'])
            ->set('closeReason', 'Period reviewed and ready for close.')
            ->call('submitCloseBudget')
            ->assertHasNoErrors()
            ->assertSet('showCloseModal', false);

        $this->assertDatabaseHas('department_budgets', [
            'id' => $budget->id,
            'status' => 'closed',
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
            'email' => Str::slug($name).'+budget-page@example.test',
            'is_active' => true,
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

    private function createBudget(Company $company, Department $department, User $creator): DepartmentBudget
    {
        return DepartmentBudget::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'allocated_amount' => 500000,
            'used_amount' => 0,
            'remaining_amount' => 500000,
            'status' => 'active',
            'created_by' => $creator->id,
        ]);
    }
}
