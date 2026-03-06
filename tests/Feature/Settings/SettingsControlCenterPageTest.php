<?php

namespace Tests\Feature\Settings;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingsControlCenterPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_settings_control_center_from_both_routes(): void
    {
        [$company, $department] = $this->createCompanyContext('Settings Center Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Settings Control Center');

        $this->actingAs($owner)
            ->get(route('settings.control-center'))
            ->assertOk()
            ->assertSee('Settings Control Center');
    }

    public function test_non_owner_cannot_open_settings_control_center(): void
    {
        [$company, $department] = $this->createCompanyContext('Settings Center Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('settings.index'))
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
            'email' => Str::slug($name).'+settings-control@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
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
