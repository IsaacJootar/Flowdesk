<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementReleaseDeskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_release_desk_and_help_when_procurement_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Purchase Order Workspace Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('procurement.release-desk'))
            ->assertOk()
            ->assertSee('Purchase Order Workspace');

        $this->actingAs($owner)
            ->get(route('procurement.release-help'))
            ->assertOk()
            ->assertSee('Purchase Order Guide');
    }

    public function test_staff_is_forbidden_from_release_desk_even_when_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Purchase Order Workspace Staff');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($staff)
            ->get(route('procurement.release-desk'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('procurement.release-help'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+procurement@example.test',
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
