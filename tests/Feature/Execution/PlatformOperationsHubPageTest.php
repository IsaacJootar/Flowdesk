<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Platform\PlatformOperationsHubPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformOperationsHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_view_operations_hub(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('platform.operations.hub'))
            ->assertOk()
            ->assertSee('Operations Hub')
            ->assertSee('Execution Ops')
            ->assertSee('Incident History')
            ->assertSee('Pilot Rollout');
    }

    public function test_non_platform_user_cannot_view_operations_hub(): void
    {
        $tenant = $this->createTenantCompany('Platform Ops Hub Access Tenant');

        $user = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Owner->value,
            'platform_role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('platform.operations.hub'))
            ->assertForbidden();
    }

    public function test_operations_hub_uses_execution_ops_recovery_threshold_config(): void
    {
        config()->set('execution.ops_recovery.older_than_minutes', 45);

        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser);

        Livewire::test(PlatformOperationsHubPage::class)
            ->call('loadData')
            ->assertSee('Threshold: 45 mins');
    }

    private function createPlatformUser(string $platformRole): User
    {
        return User::factory()->create([
            'company_id' => null,
            'department_id' => null,
            'role' => UserRole::Owner->value,
            'platform_role' => $platformRole,
            'is_active' => true,
        ]);
    }

    private function createTenantCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }
}
