<?php

namespace Tests\Feature\Requests;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestGuidesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_open_request_lifecycle_and_communications_guides(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Guides Inactive');
        $inactive = $this->createUser($company, $department, UserRole::Staff->value, [
            'is_active' => false,
        ]);

        $this->actingAs($inactive)->get(route('requests.lifecycle-desk'))->assertForbidden();
        $this->actingAs($inactive)->get(route('requests.lifecycle-help'))->assertForbidden();
        $this->actingAs($inactive)->get(route('requests.communications-help'))->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+request-guides@example.test',
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(Company $company, Department $department, string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
        ], $overrides));
    }
}

