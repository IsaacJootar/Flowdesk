<?php

namespace Tests\Feature\Fintech;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Fintech\Models\CompanyPaymentRailSetting;
use App\Enums\UserRole;
use App\Livewire\Settings\PaymentsRailsIntegrationPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsRailsIntegrationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_payments_rails_page_when_fintech_is_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Payments Rails Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'fintech_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('settings.payments-rails'))
            ->assertOk()
            ->assertSee('Payments Rails Integration')
            ->assertSee('Connect')
            ->assertSee('Test Connection');
    }

    public function test_owner_can_connect_sync_and_pause_resume_manual_ops_rail(): void
    {
        [$company, $department] = $this->createCompanyContext('Payments Rails Actions');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(PaymentsRailsIntegrationPage::class)
            ->set('connectForm.provider_key', 'manual_ops')
            ->call('connect')
            ->assertSet('feedbackMessage', 'Payment rail connected.')
            ->call('syncNow')
            ->assertSet('feedbackMessage', 'Sync completed.')
            ->call('togglePause')
            ->assertSet('feedbackMessage', 'Payment rail paused.')
            ->call('togglePause')
            ->assertSet('feedbackMessage', 'Payment rail resumed.');

        $setting = CompanyPaymentRailSetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame('manual_ops', (string) $setting->provider_key);
        $this->assertSame(CompanyPaymentRailSetting::STATUS_CONNECTED, (string) $setting->connection_status);
        $this->assertNotNull($setting->last_synced_at);

        $this->assertSame(
            4,
            TenantAuditEvent::query()
                ->where('company_id', $company->id)
                ->where('action', 'like', 'tenant.payments_rails.%')
                ->count()
        );
    }

    public function test_owner_can_run_connection_test_and_persist_test_metadata(): void
    {
        [$company, $department] = $this->createCompanyContext('Payments Rails Test');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(PaymentsRailsIntegrationPage::class)
            ->set('connectForm.provider_key', 'manual_ops')
            ->call('connect')
            ->call('testConnection')
            ->assertSet('feedbackMessage', 'Connection test completed (manual operations mode).');

        $setting = CompanyPaymentRailSetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame('passed', (string) $setting->last_test_status);
        $this->assertNotNull($setting->last_tested_at);
        $this->assertSame('Connection test completed (manual operations mode).', (string) $setting->last_test_message);
    }

    public function test_non_owner_is_forbidden_from_payments_rails_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Payments Rails Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'fintech_enabled' => true,
            'created_by' => $staff->id,
            'updated_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('settings.payments-rails'))
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
            'email' => Str::slug($name).'+fintech@example.test',
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
