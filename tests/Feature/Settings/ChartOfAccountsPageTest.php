<?php

namespace Tests\Feature\Settings;

use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\AccountingCategory;
use App\Enums\UserRole;
use App\Livewire\Settings\ChartOfAccountsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ChartOfAccountsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_and_save_chart_of_accounts_mapping(): void
    {
        [$company, $department] = $this->createCompanyContext('Chart Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        $this->get(route('settings.chart-of-accounts'))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('Spend Type to Account Code');

        Livewire::test(ChartOfAccountsPage::class)
            ->set('mappings.'.AccountingCategory::SpendOperations->value.'.account_code', '5000')
            ->set('mappings.'.AccountingCategory::SpendOperations->value.'.account_name', 'Operating Expenses')
            ->call('save')
            ->assertSet('feedbackMessage', 'Chart of Accounts saved.');

        $this->assertDatabaseHas('chart_of_account_mappings', [
            'company_id' => $company->id,
            'provider' => 'csv',
            'category_key' => AccountingCategory::SpendOperations->value,
            'account_code' => '5000',
            'account_name' => 'Operating Expenses',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'accounting.chart_of_accounts.updated',
        ]);
    }

    public function test_finance_can_save_chart_of_accounts_mapping(): void
    {
        [$company, $department] = $this->createCompanyContext('Chart Finance');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        Livewire::test(ChartOfAccountsPage::class)
            ->set('mappings.'.AccountingCategory::SpendTravel->value.'.account_code', '5100')
            ->set('mappings.'.AccountingCategory::SpendTravel->value.'.account_name', 'Travel')
            ->call('save')
            ->assertSet('feedbackMessage', 'Chart of Accounts saved.');

        $this->assertDatabaseHas('chart_of_account_mappings', [
            'company_id' => $company->id,
            'provider' => 'csv',
            'category_key' => AccountingCategory::SpendTravel->value,
            'account_code' => '5100',
            'updated_by' => $finance->id,
        ]);
    }

    public function test_auditor_can_view_but_cannot_manage_chart_of_accounts(): void
    {
        [$company, $department] = $this->createCompanyContext('Chart Auditor');
        $auditor = $this->createUser($company, $department, UserRole::Auditor->value);

        $this->actingAs($auditor)
            ->get(route('settings.chart-of-accounts'))
            ->assertOk()
            ->assertSee('View-only access')
            ->assertDontSee('Save Chart of Accounts');

        Livewire::test(ChartOfAccountsPage::class)
            ->assertSet('canManage', false);
    }

    public function test_staff_cannot_access_chart_of_accounts(): void
    {
        [$company, $department] = $this->createCompanyContext('Chart Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('settings.chart-of-accounts'))
            ->assertForbidden();
    }

    public function test_chart_of_accounts_save_does_not_touch_another_company_mapping(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Chart Company A');
        [$companyB, $departmentB] = $this->createCompanyContext('Chart Company B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        ChartOfAccountMapping::query()->withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'provider' => 'csv',
            'category_key' => AccountingCategory::SpendOperations->value,
            'account_code' => 'B-5000',
            'account_name' => 'Company B Operations',
            'created_by' => $ownerB->id,
            'updated_by' => $ownerB->id,
        ]);

        $this->actingAs($ownerA);

        Livewire::test(ChartOfAccountsPage::class)
            ->set('mappings.'.AccountingCategory::SpendOperations->value.'.account_code', 'A-5000')
            ->set('mappings.'.AccountingCategory::SpendOperations->value.'.account_name', 'Company A Operations')
            ->call('save')
            ->assertSet('feedbackMessage', 'Chart of Accounts saved.');

        $this->assertDatabaseHas('chart_of_account_mappings', [
            'company_id' => $companyA->id,
            'provider' => 'csv',
            'category_key' => AccountingCategory::SpendOperations->value,
            'account_code' => 'A-5000',
        ]);

        $this->assertDatabaseHas('chart_of_account_mappings', [
            'company_id' => $companyB->id,
            'provider' => 'csv',
            'category_key' => AccountingCategory::SpendOperations->value,
            'account_code' => 'B-5000',
            'account_name' => 'Company B Operations',
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
            'email' => Str::slug($name).'+chart@example.test',
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
