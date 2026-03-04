<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Domains\Company\Models\TenantPilotWaveOutcome;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Platform\PilotRolloutKpiPage;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_platform_operator_can_record_pilot_wave_outcome_from_ui(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenant = $this->createTenantCompany('Pilot Wave Outcome Tenant');

        $this->actingAs($platformUser);

        Livewire::test(PilotRolloutKpiPage::class)
            ->set('outcomeTenant', (string) $tenant->id)
            ->set('outcomeWaveLabel', 'wave-2')
            ->set('outcomeDecision', 'hold')
            ->set('outcomeNotes', 'Hold for one week while treasury mapping is corrected.')
            ->call('recordWaveOutcome')
            ->assertSee('Recorded Hold outcome for Pilot Wave Outcome Tenant (wave-2).');

        $this->assertDatabaseHas('tenant_pilot_wave_outcomes', [
            'company_id' => $tenant->id,
            'wave_label' => 'wave-2',
            'outcome' => 'hold',
        ]);

        $waveOutcome = TenantPilotWaveOutcome::query()
            ->where('company_id', $tenant->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($waveOutcome);
        $this->assertSame($platformUser->id, (int) $waveOutcome->decided_by_user_id);

        $auditEvent = TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.rollout.pilot_wave_outcome.recorded')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditEvent);
        $this->assertSame((int) $waveOutcome->id, (int) $auditEvent->entity_id);
    }

    public function test_cohort_progress_tracker_shows_stage_and_missing_steps_per_tenant(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $readyTenant = $this->createTenantCompany('Pilot Tracker Ready Tenant');
        $decisionPendingTenant = $this->createTenantCompany('Pilot Tracker Decision Tenant');
        $baselinePendingTenant = $this->createTenantCompany('Pilot Tracker Baseline Tenant');

        $this->createKpiCapture((int) $readyTenant->id, 'baseline', now()->subDays(20));
        $this->createKpiCapture((int) $readyTenant->id, 'pilot', now()->subDays(6));
        TenantPilotWaveOutcome::query()->create([
            'company_id' => (int) $readyTenant->id,
            'wave_label' => 'wave-1',
            'outcome' => TenantPilotWaveOutcome::OUTCOME_GO,
            'decision_at' => now()->subDay(),
            'notes' => 'Ready to move forward.',
            'metadata' => ['source' => 'test'],
            'decided_by_user_id' => (int) $platformUser->id,
        ]);

        $this->createKpiCapture((int) $decisionPendingTenant->id, 'baseline', now()->subDays(18));
        $this->createKpiCapture((int) $decisionPendingTenant->id, 'pilot', now()->subDays(5));

        $this->actingAs($platformUser);

        Livewire::test(PilotRolloutKpiPage::class)
            ->call('loadData')
            ->assertSee('Cohort Progress Tracker')
            ->assertSee('Pilot Tracker Ready Tenant')
            ->assertSee('Pilot Tracker Decision Tenant')
            ->assertSee('Pilot Tracker Baseline Tenant')
            ->assertSee('Ready for rollout')
            ->assertSee('Decision pending')
            ->assertSee('Baseline pending')
            ->assertSee('Capture baseline KPI window first.')
            ->assertSee('Missing');
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

    private function createKpiCapture(int $companyId, string $windowLabel, DateTimeInterface $capturedAt): TenantPilotKpiCapture
    {
        $windowEnd = Carbon::instance($capturedAt)->endOfDay();

        return TenantPilotKpiCapture::query()->create([
            'company_id' => $companyId,
            'window_label' => $windowLabel,
            'window_start' => $windowEnd->copy()->subDays(13)->startOfDay(),
            'window_end' => $windowEnd,
            'match_pass_rate_percent' => 80,
            'open_procurement_exceptions' => 1,
            'procurement_exception_avg_open_hours' => 12,
            'auto_reconciliation_rate_percent' => 75,
            'open_treasury_exceptions' => 1,
            'treasury_exception_avg_open_hours' => 8,
            'blocked_payout_count' => 0,
            'manual_override_count' => 0,
            'incident_count' => 0,
            'incident_rate_per_week' => 0,
            'metadata' => ['source' => 'test'],
            'notes' => 'Test capture',
            'captured_at' => $windowEnd,
            'captured_by_user_id' => null,
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
