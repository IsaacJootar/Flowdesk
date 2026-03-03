<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Platform\PilotRolloutKpiPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PilotRolloutKpiPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_view_pilot_rollout_page(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('platform.operations.pilot-rollout'))
            ->assertOk()
            ->assertSee('Pilot KPI Capture');
    }

    public function test_non_platform_user_cannot_view_pilot_rollout_page(): void
    {
        $tenant = $this->createTenantCompany('Pilot KPI Access Tenant');

        $user = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Owner->value,
            'platform_role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('platform.operations.pilot-rollout'))
            ->assertForbidden();
    }

    public function test_platform_operator_can_capture_tenant_kpi_window_from_ui(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenant = $this->createTenantCompany('Pilot KPI Capture Tenant');

        $this->actingAs($platformUser);

        Livewire::test(PilotRolloutKpiPage::class)
            ->set('captureTenant', (string) $tenant->id)
            ->set('captureWindowLabel', 'baseline')
            ->set('captureWindowDays', '14')
            ->set('captureNotes', 'Initial pilot baseline')
            ->call('captureNow')
            ->assertSee('Captured 1 tenant KPI snapshot row(s) for baseline window.');

        $this->assertDatabaseHas('tenant_pilot_kpi_captures', [
            'company_id' => $tenant->id,
            'window_label' => 'baseline',
            'notes' => 'Initial pilot baseline',
        ]);

        $capture = TenantPilotKpiCapture::query()
            ->where('company_id', $tenant->id)
            ->where('window_label', 'baseline')
            ->first();

        $this->assertNotNull($capture);
        $this->assertSame(0.0, (float) $capture->match_pass_rate_percent);
        $this->assertSame(0.0, (float) $capture->auto_reconciliation_rate_percent);
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
