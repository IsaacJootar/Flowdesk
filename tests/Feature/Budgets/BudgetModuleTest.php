<?php

namespace Tests\Feature\Budgets;

use App\Actions\Budgets\CloseDepartmentBudget;
use App\Actions\Budgets\CreateDepartmentBudget;
use App\Actions\Budgets\UpdateDepartmentBudget;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BudgetModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_budget_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget HQ');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        $budget = app(CreateDepartmentBudget::class)($owner, $this->validBudgetPayload($department));

        $this->assertSame($company->id, (int) $budget->company_id);
        $this->assertSame('active', $budget->status);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'budget.created',
            'entity_type' => DepartmentBudget::class,
            'entity_id' => $budget->id,
        ]);
    }

    public function test_finance_can_update_budget_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget Update');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $budget = $this->createBudget($company, $department, $finance);

        $this->actingAs($finance);

        app(UpdateDepartmentBudget::class)($finance, $budget, [
            ...$this->validBudgetPayload($department),
            'allocated_amount' => 850000,
        ]);

        $this->assertDatabaseHas('department_budgets', [
            'id' => $budget->id,
            'allocated_amount' => 850000,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $finance->id,
            'action' => 'budget.updated',
            'entity_type' => DepartmentBudget::class,
            'entity_id' => $budget->id,
        ]);
    }

    public function test_finance_can_close_budget_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget Close');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $budget = $this->createBudget($company, $department, $finance);

        $this->actingAs($finance);

        app(CloseDepartmentBudget::class)($finance, $budget);

        $this->assertDatabaseHas('department_budgets', [
            'id' => $budget->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $finance->id,
            'action' => 'budget.closed',
            'entity_type' => DepartmentBudget::class,
            'entity_id' => $budget->id,
        ]);
    }

    public function test_manager_cannot_create_budget(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget Manager');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        $this->actingAs($manager);
        $this->expectException(AuthorizationException::class);

        app(CreateDepartmentBudget::class)($manager, $this->validBudgetPayload($department));
    }

    public function test_overlapping_active_budget_for_same_department_is_blocked(): void
    {
        [$company, $department] = $this->createCompanyContext('Budget Overlap');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);
        $this->createBudget($company, $department, $owner, [
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]);

        try {
            app(CreateDepartmentBudget::class)($owner, [
                ...$this->validBudgetPayload($department),
                'period_start' => '2026-01-15',
                'period_end' => '2026-02-15',
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_start', $exception->errors());
        }
    }

    public function test_budget_queries_are_company_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Budget Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Budget Scope B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $budgetA = $this->createBudget($companyA, $departmentA, $ownerA);
        $budgetB = $this->createBudget($companyB, $departmentB, $ownerB);

        $this->actingAs($ownerA);

        $visibleIds = DepartmentBudget::query()->pluck('id')->all();

        $this->assertContains($budgetA->id, $visibleIds);
        $this->assertNotContains($budgetB->id, $visibleIds);
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $companyName): array
    {
        $company = Company::query()->create([
            'name' => $companyName,
            'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($companyName).'+company@example.test',
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBudget(
        Company $company,
        Department $department,
        User $creator,
        array $overrides = []
    ): DepartmentBudget {
        return DepartmentBudget::query()->create(array_merge(
            [
                'company_id' => $company->id,
                'department_id' => $department->id,
                'period_type' => 'monthly',
                'period_start' => '2026-01-01',
                'period_end' => '2026-01-31',
                'allocated_amount' => 600000,
                'used_amount' => 0,
                'remaining_amount' => 600000,
                'status' => 'active',
                'created_by' => $creator->id,
            ],
            $overrides
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function validBudgetPayload(Department $department): array
    {
        return [
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'allocated_amount' => 700000,
        ];
    }
}

