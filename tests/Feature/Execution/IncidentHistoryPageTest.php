<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Platform\IncidentHistoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_can_view_incident_history_page(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);

        $this->actingAs($platformUser)
            ->get(route('platform.operations.incident-history'))
            ->assertOk()
            ->assertSee('Incident History')
            ->assertSee('7-Day Trend');
    }

    public function test_non_platform_user_cannot_view_incident_history_page(): void
    {
        $tenant = $this->createTenantCompany('Incident Access Tenant');

        $user = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Owner->value,
            'platform_role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('platform.operations.incident-history'))
            ->assertForbidden();
    }

    public function test_pipeline_filter_limits_incident_rows(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenant = $this->createTenantCompany('Incident Filter Tenant');

        TenantAuditEvent::query()->create([
            'company_id' => $tenant->id,
            'actor_user_id' => $platformUser->id,
            'action' => 'tenant.execution.billing.retry_requested',
            'description' => 'Billing retry from ops.',
            'event_at' => now()->subMinutes(4),
        ]);

        TenantAuditEvent::query()->create([
            'company_id' => $tenant->id,
            'actor_user_id' => $platformUser->id,
            'action' => 'tenant.execution.webhook.manual_failed',
            'description' => 'Webhook manual reconcile failed.',
            'event_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($platformUser);

        Livewire::test(IncidentHistoryPage::class)
            ->call('loadData')
            ->set('pipelineFilter', 'billing')
            ->assertSee('Billing retry requested')
            ->assertDontSee('Webhook manual reconcile failed');
    }

    public function test_operator_can_export_incident_history_csv(): void
    {
        Carbon::setTestNow('2026-03-02 10:11:12');

        try {
            $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
            $tenant = $this->createTenantCompany('Incident Export Tenant');

            TenantAuditEvent::query()->create([
                'company_id' => $tenant->id,
                'actor_user_id' => $platformUser->id,
                'action' => 'tenant.execution.auto_recovery.run_summary',
                'description' => 'Auto recovery summary emitted.',
                'metadata' => [
                    'pipeline' => 'payout',
                    'matched' => 3,
                    'processed' => 2,
                    'skipped' => 1,
                    'rejected' => 0,
                    'older_than_minutes' => 30,
                ],
                'event_at' => now()->subMinute(),
            ]);

            $this->actingAs($platformUser);

            Livewire::test(IncidentHistoryPage::class)
                ->call('loadData')
                ->call('exportCsv')
                ->assertFileDownloaded('incident_history_20260302_101112.csv');
        } finally {
            Carbon::setTestNow();
        }
    }


    public function test_incident_type_filter_can_show_alert_delivery_events(): void
    {
        $platformUser = $this->createPlatformUser(PlatformUserRole::PlatformOpsAdmin->value);
        $tenant = $this->createTenantCompany('Incident Delivery Filter Tenant');

        TenantAuditEvent::query()->create([
            'company_id' => $tenant->id,
            'actor_user_id' => null,
            'action' => 'tenant.execution.alert.notification.sent',
            'description' => 'Execution alert summary delivered via email notifications.',
            'metadata' => [
                'pipeline' => 'billing',
                'channel' => 'email',
            ],
            'event_at' => now()->subMinutes(3),
        ]);

        TenantAuditEvent::query()->create([
            'company_id' => $tenant->id,
            'actor_user_id' => null,
            'action' => 'tenant.execution.alert.summary_emitted',
            'description' => 'Execution alert threshold breached during ops summary run.',
            'metadata' => [
                'pipeline' => 'billing',
            ],
            'event_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($platformUser);

        Livewire::test(IncidentHistoryPage::class)
            ->call('loadData')
            ->set('incidentTypeFilter', 'alert_delivery')
            ->assertSee('Execution alert delivery sent')
            ->assertDontSee('Execution alert summary');
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


