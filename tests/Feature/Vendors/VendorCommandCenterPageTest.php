<?php

namespace Tests\Feature\Vendors;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Livewire\Vendors\VendorCommandCenterPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class VendorCommandCenterPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_vendor_command_center(): void
    {
        [$company, $department] = $this->createCompanyContext('Vendor Management Workspace Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $this->enableVendorModule($company, $owner);

        $this->actingAs($owner)
            ->get(route('vendors.index'))
            ->assertOk()
            ->assertSee('Vendor Management Workspace');
    }

    public function test_vendor_command_center_rows_are_tenant_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Vendor Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Vendor Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $this->enableVendorModule($companyA, $ownerA);
        $this->enableVendorModule($companyB, $ownerB);

        Vendor::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Tenant A Vendor',
            'vendor_type' => 'supplier',
            'contact_person' => '',
            'phone' => '08000000001',
            'email' => '',
            'address' => 'A Street',
            'bank_name' => '',
            'bank_code' => '',
            'account_name' => '',
            'account_number' => '',
            'is_active' => true,
        ]);

        Vendor::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Tenant B Vendor',
            'vendor_type' => 'supplier',
            'contact_person' => '',
            'phone' => '08000000002',
            'email' => '',
            'address' => 'B Street',
            'bank_name' => '',
            'bank_code' => '',
            'account_name' => '',
            'account_number' => '',
            'is_active' => true,
        ]);

        $this->actingAs($ownerA);

        Livewire::test(VendorCommandCenterPage::class)
            ->call('loadData')
            ->assertSee('Tenant A Vendor')
            ->assertDontSee('Tenant B Vendor');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+vendor-command@example.test',
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

    private function enableVendorModule(Company $company, User $actor): void
    {
        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'vendors_enabled' => true,
            'requests_enabled' => true,
            'procurement_enabled' => true,
            'reports_enabled' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}



