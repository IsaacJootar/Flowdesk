<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantExecutionUsageGuidePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_tenant_user_can_view_execution_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Execution Help Tenant');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance)
            ->get(route('execution.help'))
            ->assertOk()
            ->assertSee('Payment Movement Guide')
            ->assertSee('Send Payment')
            ->assertSee('Retry Payment');
    }

    public function test_staff_and_platform_users_cannot_view_execution_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Execution Help Forbidden');

        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('execution.help'))
            ->assertForbidden();

        $platformUser = $this->createUser($company, $department, UserRole::Owner->value, PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('execution.help'))
            ->assertForbidden();
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+execution-help@example.test',
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

    private function createUser(Company $company, Department $department, string $role, ?string $platformRole = null): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'platform_role' => $platformRole,
            'is_active' => true,
        ]);
    }
}
