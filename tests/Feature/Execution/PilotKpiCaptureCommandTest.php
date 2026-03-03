<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PilotKpiCaptureCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_kpi_capture_command_captures_all_eligible_tenants(): void
    {
        $tenant = $this->createTenantCompany('Pilot KPI Command Tenant');

        TenantFeatureEntitlement::query()->create([
            'company_id' => $tenant->id,
            'procurement_enabled' => true,
            'treasury_enabled' => true,
        ]);

        $this->artisan('rollout:pilot:capture-kpis', [
            '--label' => 'pilot',
            '--window-days' => 14,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('tenant_pilot_kpi_captures', [
            'company_id' => $tenant->id,
            'window_label' => 'pilot',
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
