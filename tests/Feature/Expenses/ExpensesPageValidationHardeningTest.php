<?php

namespace Tests\Feature\Expenses;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Livewire\Expenses\ExpensesPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ExpensesPageValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenses_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Filter Harden');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        Livewire::test(ExpensesPage::class)
            ->set('statusFilter', 'unexpected_status')
            ->assertSet('statusFilter', 'all')
            ->set('paymentMethodFilter', 'crypto')
            ->assertSet('paymentMethodFilter', 'all')
            ->set('departmentFilter', '-2')
            ->assertSet('departmentFilter', 'all')
            ->set('vendorFilter', 'abc')
            ->assertSet('vendorFilter', 'all')
            ->set('dateFrom', 'invalid-date')
            ->assertSet('dateFrom', '')
            ->set('dateTo', '2026-99-99')
            ->assertSet('dateTo', '')
            ->set('perPage', 999)
            ->assertSet('perPage', 10)
            ->set('dateFrom', '2026-03-10')
            ->set('dateTo', '2026-03-01')
            ->assertSet('dateTo', '');
    }

    public function test_save_rejects_foreign_vendor_and_foreign_paid_by_user(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Expense Save Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Expense Save Scope B');

        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);
        $foreignVendor = $this->createVendor($companyB);

        $this->actingAs($financeA);

        Livewire::test(ExpensesPage::class)
            ->set('form.department_id', (string) $departmentA->id)
            ->set('form.vendor_id', (string) $foreignVendor->id)
            ->set('form.title', 'Cross tenant save guard')
            ->set('form.description', 'Validation hardening check')
            ->set('form.amount', '120000')
            ->set('form.expense_date', now()->toDateString())
            ->set('form.payment_method', 'transfer')
            ->set('form.paid_by_user_id', (string) $financeB->id)
            ->call('save')
            ->assertHasErrors([
                'form.vendor_id',
                'form.paid_by_user_id',
            ]);
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+expense-page@example.test',
            'is_active' => true,
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

    private function createVendor(Company $company): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => 'Vendor '.Str::lower(Str::random(5)),
            'vendor_type' => 'supplier',
            'contact_person' => 'Vendor Contact',
            'phone' => '08000000000',
            'email' => Str::lower(Str::random(5)).'@vendor.test',
            'address' => 'Vendor Address',
            'bank_name' => 'Example Bank',
            'bank_code' => '999',
            'account_name' => 'Vendor Account',
            'account_number' => (string) random_int(10000000, 99999999),
            'notes' => 'Vendor seed',
            'is_active' => true,
        ]);
    }
}

