<?php

namespace Tests\Feature\Auth;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformOperatorTenantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_with_company_context_is_forbidden_from_tenant_routes(): void
    {
        [$company, $department] = $this->createCompanyContext('Platform Tenant Boundary');

        $platformUser = User::factory()->create([
            'company_id' => (int) $company->id,
            'department_id' => (int) $department->id,
            'role' => UserRole::Owner->value,
            'platform_role' => PlatformUserRole::PlatformOpsAdmin->value,
            'is_active' => true,
        ]);

        $this->actingAs($platformUser)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_platform_operator_can_access_platform_routes(): void
    {
        [$company, $department] = $this->createCompanyContext('Platform Route Access');

        $platformUser = User::factory()->create([
            'company_id' => (int) $company->id,
            'department_id' => (int) $department->id,
            'role' => UserRole::Owner->value,
            'platform_role' => PlatformUserRole::PlatformOpsAdmin->value,
            'is_active' => true,
        ]);

        $this->actingAs($platformUser)
            ->get(route('platform.dashboard'))
            ->assertOk();
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+boundary@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => (int) $company->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        return [$company, $department];
    }
}
