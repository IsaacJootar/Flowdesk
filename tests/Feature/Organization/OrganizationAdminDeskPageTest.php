<?php

namespace Tests\Feature\Organization;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Organization\OrganizationAdminDeskPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationAdminDeskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_organization_admin_desk(): void
    {
        [$company, $department] = $this->createCompanyContext('Org Admin Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('organization.admin-desk'))
            ->assertOk()
            ->assertSee('Organization Admin Desk');
    }

    public function test_non_owner_cannot_open_organization_admin_desk(): void
    {
        [$company, $department] = $this->createCompanyContext('Org Admin Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('organization.admin-desk'))
            ->assertForbidden();
    }

    public function test_organization_admin_rows_are_tenant_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Org Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Org Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        Department::query()->create([
            'company_id' => $companyA->id,
            'name' => 'A Missing Head',
            'code' => 'AMH',
            'manager_user_id' => null,
            'is_active' => true,
        ]);

        Department::query()->create([
            'company_id' => $companyB->id,
            'name' => 'B Missing Head',
            'code' => 'BMH',
            'manager_user_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($ownerA);

        Livewire::test(OrganizationAdminDeskPage::class)
            ->call('loadData')
            ->assertSee('A Missing Head')
            ->assertDontSee('B Missing Head');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+org-admin@example.test',
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
}
