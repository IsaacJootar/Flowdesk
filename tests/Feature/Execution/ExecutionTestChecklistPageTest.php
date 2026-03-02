<?php

namespace Tests\Feature\Execution;

use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutionTestChecklistPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_view_execution_test_checklist_page(): void
    {
        $platformUser = User::factory()->create([
            'company_id' => null,
            'department_id' => null,
            'role' => UserRole::Owner->value,
            'platform_role' => PlatformUserRole::PlatformOpsAdmin->value,
            'is_active' => true,
        ]);

        $this->actingAs($platformUser)
            ->get(route('platform.operations.execution-checklist'))
            ->assertOk()
            ->assertSee('Execution Test Checklist');
    }

    public function test_non_platform_user_cannot_view_execution_test_checklist_page(): void
    {
        $tenantOwner = User::factory()->create([
            'role' => UserRole::Owner->value,
            'platform_role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($tenantOwner)
            ->get(route('platform.operations.execution-checklist'))
            ->assertForbidden();
    }
}
