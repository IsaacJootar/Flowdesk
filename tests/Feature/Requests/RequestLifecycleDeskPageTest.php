<?php

namespace Tests\Feature\Requests;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestLifecycleDeskPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RequestLifecycleDeskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_lifecycle_desk_and_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Lifecycle Desk Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('requests.lifecycle-desk'))
            ->assertOk()
            ->assertSee('Request Lifecycle Desk');

        $this->actingAs($owner)
            ->get(route('requests.lifecycle-help'))
            ->assertOk()
            ->assertSee('How to Run the Lifecycle Desk');
    }

    public function test_staff_cannot_open_lifecycle_desk_or_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Lifecycle Desk Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('requests.lifecycle-desk'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('requests.lifecycle-help'))
            ->assertForbidden();
    }

    public function test_lifecycle_rows_are_tenant_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Lifecycle Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Lifecycle Scope B');

        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);

        $requesterA = $this->createUser($companyA, $departmentA, UserRole::Staff->value);
        $requesterB = $this->createUser($companyB, $departmentB, UserRole::Staff->value);

        SpendRequest::query()->create([
            'company_id' => $companyA->id,
            'request_code' => 'REQ-LIFE-A-001',
            'requested_by' => $requesterA->id,
            'department_id' => $departmentA->id,
            'title' => 'Tenant A lifecycle row',
            'amount' => 120000,
            'currency' => 'NGN',
            'status' => 'approved',
            'created_by' => $financeA->id,
            'updated_by' => $financeA->id,
        ]);

        SpendRequest::query()->create([
            'company_id' => $companyB->id,
            'request_code' => 'REQ-LIFE-B-001',
            'requested_by' => $requesterB->id,
            'department_id' => $departmentB->id,
            'title' => 'Tenant B lifecycle row',
            'amount' => 190000,
            'currency' => 'NGN',
            'status' => 'approved',
            'created_by' => $financeB->id,
            'updated_by' => $financeB->id,
        ]);

        $this->actingAs($financeA);

        Livewire::test(RequestLifecycleDeskPage::class)
            ->call('loadData')
            ->assertSee('REQ-LIFE-A-001')
            ->assertDontSee('REQ-LIFE-B-001');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+lifecycle@example.test',
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
