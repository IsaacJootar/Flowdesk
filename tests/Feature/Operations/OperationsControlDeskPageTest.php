<?php

namespace Tests\Feature\Operations;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Livewire\Operations\VendorPayablesDeskPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsControlDeskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_operations_control_desk(): void
    {
        [$company, $department] = $this->createCompanyContext('Ops Desk Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('operations.control-desk'))
            ->assertOk()
            ->assertSee('Operations Desks');
    }

    public function test_staff_cannot_view_operations_control_desk(): void
    {
        [$company, $department] = $this->createCompanyContext('Ops Desk Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('operations.control-desk'))
            ->assertForbidden();
    }

    public function test_vendor_payables_rows_are_tenant_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Ops Desk Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Ops Desk Scope B');

        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);

        $vendorA = Vendor::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Tenant A Vendor',
            'vendor_type' => 'service',
            'is_active' => true,
        ]);

        $vendorB = Vendor::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Tenant B Vendor',
            'vendor_type' => 'service',
            'is_active' => true,
        ]);

        VendorInvoice::query()->create([
            'company_id' => $companyA->id,
            'vendor_id' => $vendorA->id,
            'invoice_number' => 'INV-OPS-A-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 120000,
            'paid_amount' => 0,
            'outstanding_amount' => 120000,
            'status' => VendorInvoice::STATUS_UNPAID,
            'created_by' => $financeA->id,
            'updated_by' => $financeA->id,
        ]);

        VendorInvoice::query()->create([
            'company_id' => $companyB->id,
            'vendor_id' => $vendorB->id,
            'invoice_number' => 'INV-OPS-B-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 150000,
            'paid_amount' => 0,
            'outstanding_amount' => 150000,
            'status' => VendorInvoice::STATUS_UNPAID,
            'created_by' => $financeB->id,
            'updated_by' => $financeB->id,
        ]);

        $this->actingAs($financeA);

        Livewire::test(VendorPayablesDeskPage::class)
            ->call('loadData')
            ->assertSee('INV-OPS-A-001')
            ->assertDontSee('INV-OPS-B-001');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+ops-desk@example.test',
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
