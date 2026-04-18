<?php

namespace Tests\Feature\Auth;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
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

    public function test_tenant_user_only_sees_own_company_payout_attempts(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Tenant Boundary A');
        [$companyB, $departmentB] = $this->createCompanyContext('Tenant Boundary B');
        $tenantUserA = $this->createTenantUser($companyA, $departmentA);
        $tenantUserB = $this->createTenantUser($companyB, $departmentB);
        $attemptA = $this->createPayoutAttempt($companyA, $departmentA, $tenantUserA, 'FD-SCOPE-A');
        $attemptB = $this->createPayoutAttempt($companyB, $departmentB, $tenantUserB, 'FD-SCOPE-B');

        $this->actingAs($tenantUserA);

        $visibleIds = RequestPayoutExecutionAttempt::query()->pluck('id')->all();

        $this->assertContains($attemptA->id, $visibleIds);
        $this->assertNotContains($attemptB->id, $visibleIds);
    }

    public function test_platform_operator_with_company_context_can_still_see_all_payout_attempts_on_platform_surface(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Platform Global A');
        [$companyB, $departmentB] = $this->createCompanyContext('Platform Global B');
        $platformUser = User::factory()->create([
            'company_id' => (int) $companyA->id,
            'department_id' => (int) $departmentA->id,
            'role' => UserRole::Owner->value,
            'platform_role' => PlatformUserRole::PlatformOpsAdmin->value,
            'is_active' => true,
        ]);
        $tenantUserB = $this->createTenantUser($companyB, $departmentB);
        $attemptA = $this->createPayoutAttempt($companyA, $departmentA, $platformUser, 'FD-PLATFORM-A');
        $attemptB = $this->createPayoutAttempt($companyB, $departmentB, $tenantUserB, 'FD-PLATFORM-B');

        $this->actingAs($platformUser);

        $visibleIds = RequestPayoutExecutionAttempt::query()->pluck('id')->all();

        $this->assertContains($attemptA->id, $visibleIds);
        $this->assertContains($attemptB->id, $visibleIds);
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

    private function createTenantUser(Company $company, Department $department): User
    {
        return User::factory()->create([
            'company_id' => (int) $company->id,
            'department_id' => (int) $department->id,
            'role' => UserRole::Finance->value,
            'platform_role' => null,
            'is_active' => true,
        ]);
    }

    private function createPayoutAttempt(
        Company $company,
        Department $department,
        User $user,
        string $code
    ): RequestPayoutExecutionAttempt {
        $request = SpendRequest::query()->create([
            'company_id' => (int) $company->id,
            'request_code' => $code,
            'requested_by' => (int) $user->id,
            'department_id' => (int) $department->id,
            'title' => $code.' request',
            'amount' => 25000,
            'approved_amount' => 25000,
            'currency' => 'NGN',
            'status' => 'execution_queued',
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        return RequestPayoutExecutionAttempt::query()->create([
            'company_id' => (int) $company->id,
            'request_id' => (int) $request->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':'.$code,
            'execution_status' => 'queued',
            'amount' => 25000,
            'currency_code' => 'NGN',
            'queued_at' => now(),
            'attempt_count' => 1,
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);
    }
}
