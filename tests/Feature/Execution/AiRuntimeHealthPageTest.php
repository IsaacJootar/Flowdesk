<?php

namespace Tests\Feature\Execution;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Company\Models\Company;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Platform\AiRuntimeHealthPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiRuntimeHealthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_view_ai_runtime_health_page(): void
    {
        config()->set('ai.runtime.provider', 'none');

        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('platform.operations.ai-runtime-health'))
            ->assertOk()
            ->assertSee('AI Runtime Health & Capability Monitor')
            ->assertSee('Recent Receipt Analyses');
    }

    public function test_non_platform_user_cannot_view_ai_runtime_health_page(): void
    {
        config()->set('ai.runtime.provider', 'none');

        $tenant = $this->createTenantCompany('AI Runtime Access Tenant');

        $user = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Owner->value,
            'platform_role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('platform.operations.ai-runtime-health'))
            ->assertForbidden();
    }

    public function test_runtime_health_page_renders_cross_tenant_receipt_analysis_activity(): void
    {
        config()->set('ai.runtime.provider', 'none');

        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenantA = $this->createTenantCompany('AI Runtime Tenant A');
        $tenantB = $this->createTenantCompany('AI Runtime Tenant B');

        ActivityLog::query()->withoutGlobalScopes()->create([
            'company_id' => $tenantA->id,
            'user_id' => null,
            'action' => 'expense.receipt.analysis.generated',
            'entity_type' => 'expense_receipt',
            'entity_id' => 1001,
            'metadata' => [
                'engine' => 'model_assisted',
                'ai_model' => 'qwen2.5:7b-instruct',
                'confidence' => 84,
                'fallback_used' => false,
            ],
            'created_at' => now()->subMinutes(4),
        ]);

        ActivityLog::query()->withoutGlobalScopes()->create([
            'company_id' => $tenantB->id,
            'user_id' => null,
            'action' => 'expense.receipt.analysis.generated',
            'entity_type' => 'expense_receipt',
            'entity_id' => 1002,
            'metadata' => [
                'engine' => 'deterministic',
                'ai_model' => null,
                'confidence' => 32,
                'fallback_used' => true,
            ],
            'created_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($platformUser);

        Livewire::test(AiRuntimeHealthPage::class)
            ->call('loadData')
            ->assertSee('Model-Assisted Analyses')
            ->assertSee('Deterministic Analyses')
            ->assertSee('AI Runtime Tenant A')
            ->assertSee('AI Runtime Tenant B')
            ->assertSee('model_assisted')
            ->assertSee('deterministic');
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
