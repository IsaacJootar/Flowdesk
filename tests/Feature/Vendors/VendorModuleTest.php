<?php

namespace Tests\Feature\Vendors;

use App\Actions\Vendors\CreateVendor;
use App\Actions\Vendors\DeleteVendor;
use App\Actions\Vendors\UpdateVendor;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_vendor_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme HQ');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $payload = $this->validVendorPayload();

        $this->actingAs($owner);

        $vendor = app(CreateVendor::class)($owner, $payload);

        $this->assertSame($company->id, (int) $vendor->company_id);
        $this->assertSame($payload['name'], $vendor->name);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'vendor.created',
            'entity_type' => Vendor::class,
            'entity_id' => $vendor->id,
        ]);
    }

    public function test_owner_can_update_vendor_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Ops');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        $this->actingAs($owner);

        app(UpdateVendor::class)($owner, $vendor, [
            ...$this->validVendorPayload(),
            'name' => 'Updated Vendor Name',
            'email' => 'updated.vendor@example.test',
        ]);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Updated Vendor Name',
            'email' => 'updated.vendor@example.test',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'vendor.updated',
            'entity_type' => Vendor::class,
            'entity_id' => $vendor->id,
        ]);
    }

    public function test_update_vendor_requires_at_least_one_change(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Vendor No Change');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        $this->actingAs($owner);

        try {
            app(UpdateVendor::class)($owner, $vendor, [
                'name' => (string) $vendor->name,
                'vendor_type' => (string) $vendor->vendor_type,
                'contact_person' => (string) $vendor->contact_person,
                'phone' => (string) $vendor->phone,
                'email' => (string) $vendor->email,
                'address' => (string) $vendor->address,
                'bank_name' => (string) $vendor->bank_name,
                'account_name' => (string) $vendor->account_name,
                'account_number' => (string) $vendor->account_number,
                'notes' => (string) $vendor->notes,
                'is_active' => (bool) $vendor->is_active,
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('no_changes', $exception->errors());
        }
    }

    public function test_owner_can_delete_vendor_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Admin');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        $this->actingAs($owner);

        app(DeleteVendor::class)($owner, $vendor);

        $this->assertSoftDeleted('vendors', ['id' => $vendor->id]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'vendor.deleted',
            'entity_type' => Vendor::class,
            'entity_id' => $vendor->id,
        ]);
    }

    public function test_finance_can_create_vendor(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Finance');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $payload = $this->validVendorPayload();

        $this->actingAs($finance);

        $vendor = app(CreateVendor::class)($finance, $payload);

        $this->assertSame($company->id, (int) $vendor->company_id);
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => $payload['name'],
        ]);
    }

    public function test_manager_cannot_create_vendor(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Manager');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        $this->actingAs($manager);
        $this->expectException(AuthorizationException::class);

        app(CreateVendor::class)($manager, $this->validVendorPayload());
    }

    public function test_staff_cannot_update_vendor(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);
        $vendor = $this->createVendor($company);

        $this->actingAs($staff);
        $this->expectException(AuthorizationException::class);

        app(UpdateVendor::class)($staff, $vendor, $this->validVendorPayload());
    }

    public function test_vendor_queries_are_company_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Acme A');
        [$companyB, $departmentB] = $this->createCompanyContext('Acme B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);

        $vendorA = $this->createVendor($companyA, ['name' => 'Vendor A']);
        $vendorB = $this->createVendor($companyB, ['name' => 'Vendor B']);

        $this->actingAs($ownerA);

        $visibleIds = Vendor::query()->pluck('id')->all();

        $this->assertContains($vendorA->id, $visibleIds);
        $this->assertNotContains($vendorB->id, $visibleIds);
    }

    public function test_user_cannot_modify_other_company_vendor(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Acme Security A');
        [$companyB, $departmentB] = $this->createCompanyContext('Acme Security B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $foreignVendor = $this->createVendor($companyB);

        $this->actingAs($ownerA);
        $this->expectException(AuthorizationException::class);

        app(UpdateVendor::class)($ownerA, $foreignVendor, $this->validVendorPayload());
    }

    public function test_validation_errors_are_thrown_for_empty_payload(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Validation');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        try {
            app(CreateVendor::class)($owner, [
                'name' => '',
                'vendor_type' => '',
                'contact_person' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'bank_name' => '',
                'account_name' => '',
                'account_number' => '',
                'notes' => '',
                'is_active' => true,
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('vendor_type', $errors);
            $this->assertArrayHasKey('contact_person', $errors);
            $this->assertArrayHasKey('phone', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('address', $errors);
            $this->assertArrayHasKey('bank_name', $errors);
            $this->assertArrayHasKey('account_name', $errors);
            $this->assertArrayHasKey('account_number', $errors);
            $this->assertArrayHasKey('notes', $errors);
        }
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $companyName): array
    {
        $company = Company::query()->create([
            'name' => $companyName,
            'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($companyName).'+company@example.test',
            'is_active' => true,
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createVendor(Company $company, array $overrides = []): Vendor
    {
        return Vendor::query()->create(array_merge(
            $this->validVendorPayload(),
            ['company_id' => $company->id],
            $overrides
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function validVendorPayload(): array
    {
        $suffix = Str::lower(Str::random(6));

        return [
            'name' => 'Vendor '.$suffix,
            'vendor_type' => 'supplier',
            'contact_person' => 'Contact '.$suffix,
            'phone' => '0800000'.random_int(1000, 9999),
            'email' => 'vendor.'.$suffix.'@example.test',
            'address' => '12 Example Street',
            'bank_name' => 'Example Bank',
            'account_name' => 'Vendor Account '.$suffix,
            'account_number' => (string) random_int(10000000, 99999999),
            'notes' => 'Testing vendor notes',
            'is_active' => true,
        ];
    }
}
