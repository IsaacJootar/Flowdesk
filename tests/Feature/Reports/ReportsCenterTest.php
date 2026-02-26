<?php

namespace Tests\Feature\Reports;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Reports\ReportsCenterPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_unified_reports_center(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_staff_cannot_open_unified_reports_center(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_manager_can_render_reports_center_component(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Manager');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        $this->actingAs($manager);

        Livewire::test(ReportsCenterPage::class)
            ->call('loadData')
            ->assertSee('Unified Reports Center')
            ->assertSee('Requests')
            ->assertSee('Posted Expenses')
            ->assertSee('Assets')
            ->assertSee('Active Budgets');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+company@example.test',
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
}
