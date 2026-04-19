<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\AccountingIntegration;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\AccountingProvider;
use App\Enums\UserRole;
use App\Livewire\Settings\AccountingIntegrationsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingIntegrationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_and_disable_provider_shell(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Integrations Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('settings.accounting-integrations'))
            ->assertOk()
            ->assertSee('Provider Readiness')
            ->assertSee('QuickBooks');

        Livewire::actingAs($owner)
            ->test(AccountingIntegrationsPage::class)
            ->call('setStatus', AccountingProvider::QuickBooks->value, 'disabled')
            ->assertSet('feedbackMessage', 'QuickBooks marked disabled.');

        $this->assertDatabaseHas('accounting_integrations', [
            'company_id' => $company->id,
            'provider' => AccountingProvider::QuickBooks->value,
            'status' => 'disabled',
            'updated_by' => $owner->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'accounting.integration.status_updated',
        ]);
    }

    public function test_finance_can_mark_provider_available(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Integrations Finance');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        AccountingIntegration::query()->create([
            'company_id' => (int) $company->id,
            'provider' => AccountingProvider::Xero->value,
            'status' => 'disabled',
            'created_by' => (int) $finance->id,
            'updated_by' => (int) $finance->id,
        ]);

        Livewire::actingAs($finance)
            ->test(AccountingIntegrationsPage::class)
            ->call('setStatus', AccountingProvider::Xero->value, 'disconnected')
            ->assertSet('feedbackMessage', 'Xero marked disconnected.');

        $this->assertDatabaseHas('accounting_integrations', [
            'company_id' => $company->id,
            'provider' => AccountingProvider::Xero->value,
            'status' => 'disconnected',
        ]);
    }

    public function test_auditor_can_view_but_not_manage_provider_shell(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Integrations Auditor');
        $auditor = $this->createUser($company, $department, UserRole::Auditor->value);

        $this->actingAs($auditor)
            ->get(route('settings.accounting-integrations'))
            ->assertOk()
            ->assertSee('View only')
            ->assertDontSee('Disable');

        Livewire::actingAs($auditor)
            ->test(AccountingIntegrationsPage::class)
            ->assertSet('canManage', false);
    }

    public function test_staff_cannot_access_provider_shell(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Integrations Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('settings.accounting-integrations'))
            ->assertForbidden();
    }

    public function test_provider_status_is_company_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Accounting Integrations A');
        [$companyB, $departmentB] = $this->createCompanyContext('Accounting Integrations B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        AccountingIntegration::query()->withoutGlobalScopes()->create([
            'company_id' => (int) $companyB->id,
            'provider' => AccountingProvider::Sage->value,
            'status' => 'disabled',
            'created_by' => (int) $ownerB->id,
            'updated_by' => (int) $ownerB->id,
        ]);

        Livewire::actingAs($ownerA)
            ->test(AccountingIntegrationsPage::class)
            ->call('setStatus', AccountingProvider::Sage->value, 'disconnected')
            ->assertSet('feedbackMessage', 'Sage marked disconnected.');

        $this->assertDatabaseHas('accounting_integrations', [
            'company_id' => $companyA->id,
            'provider' => AccountingProvider::Sage->value,
            'status' => 'disconnected',
        ]);

        $this->assertDatabaseHas('accounting_integrations', [
            'company_id' => $companyB->id,
            'provider' => AccountingProvider::Sage->value,
            'status' => 'disabled',
        ]);
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+integrations@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Finance',
            'code' => 'FIN',
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
