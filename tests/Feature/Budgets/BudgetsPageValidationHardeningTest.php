<?php

namespace Tests\Feature\Budgets;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
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
}

