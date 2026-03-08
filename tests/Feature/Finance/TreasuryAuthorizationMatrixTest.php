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

class TreasuryAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_is_forbidden_from_treasury_workspaces_when_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Auth Matrix Staff');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'treasury_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($staff)->get(route('treasury.reconciliation'))->assertForbidden();
        $this->actingAs($staff)->get(route('treasury.reconciliation-help'))->assertForbidden();
        $this->actingAs($staff)->get(route('treasury.reconciliation-exceptions'))->assertForbidden();
        $this->actingAs($staff)->get(route('treasury.payment-runs'))->assertForbidden();
        $this->actingAs($staff)->get(route('treasury.cash-position'))->assertForbidden();
    }

    public function test_auditor_can_open_treasury_read_workspaces_when_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Auth Matrix Auditor');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $auditor = $this->createUser($company, $department, UserRole::Auditor->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'treasury_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($auditor)->get(route('treasury.reconciliation'))->assertOk();
        $this->actingAs($auditor)->get(route('treasury.reconciliation-help'))->assertOk();
        $this->actingAs($auditor)->get(route('treasury.reconciliation-exceptions'))->assertOk();
        $this->actingAs($auditor)->get(route('treasury.payment-runs'))->assertOk();
        $this->actingAs($auditor)->get(route('treasury.cash-position'))->assertOk();
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+treasury-auth@example.test',
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

