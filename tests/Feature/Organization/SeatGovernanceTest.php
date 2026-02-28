<?php

namespace Tests\Feature\Organization;

use App\Actions\Company\CreateCompanyUser;
use App\Actions\Company\UpdateCompanyUserAssignment;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantSubscription;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SeatGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_is_blocked_when_tenant_seat_limit_is_reached(): void
    {
        [$company, $department, $owner] = $this->createCompanyContextWithOwner();
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'seat_limit' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $action = app(CreateCompanyUser::class);

        try {
            $action($owner, [
                'name' => 'Second User',
                'email' => 'second.user@example.test',
                'phone' => null,
                'gender' => 'other',
                'password' => 'Secret123!',
                'role' => UserRole::Staff->value,
                'department_id' => $department->id,
                'reports_to_user_id' => $owner->id,
                'notification_channels' => ['email'],
            ]);

            $this->fail('Expected validation exception for seat limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seat_limit', $exception->errors());
        }
    }

    public function test_activating_inactive_user_is_blocked_when_tenant_seat_limit_is_reached(): void
    {
        [$company, $department, $owner] = $this->createCompanyContextWithOwner();
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'seat_limit' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $inactiveUser = User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => UserRole::Staff->value,
            'is_active' => false,
        ]);

        $action = app(UpdateCompanyUserAssignment::class);

        try {
            $action($owner, $inactiveUser, [
                'role' => UserRole::Staff->value,
                'department_id' => $department->id,
                'reports_to_user_id' => $owner->id,
                'is_active' => true,
            ]);

            $this->fail('Expected validation exception for seat limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seat_limit', $exception->errors());
        }

        $this->assertFalse((bool) $inactiveUser->fresh()->is_active);
    }

    /**
     * @return array{0:Company,1:Department,2:User}
     */
    private function createCompanyContextWithOwner(): array
    {
        $company = Company::query()->create([
            'name' => 'Seat Governance Co',
            'slug' => 'seat-governance-'.Str::lower(Str::random(6)),
            'email' => 'seat-governance@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => UserRole::Owner->value,
            'is_active' => true,
        ]);

        return [$company, $department, $owner];
    }
}

