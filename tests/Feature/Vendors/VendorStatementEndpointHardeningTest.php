<?php

namespace Tests\Feature\Vendors;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorStatementEndpointHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_export_endpoint_is_rate_limited_per_tenant(): void
    {
        config()->set('security.rate_limits.tenant_exports_per_minute', 1);

        [$company, $department] = $this->createCompanyContext('Vendor Export Throttle');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        $endpoint = route('vendors.statement.export.csv', ['vendor' => $vendor->id]);
        $client = $this->actingAs($owner)->withServerVariables(['REMOTE_ADDR' => '198.51.100.20']);

        $client->get($endpoint)->assertOk()->assertDownload();
        $client->get($endpoint)->assertStatus(429);
    }

    public function test_statement_filters_validate_date_order_and_status(): void
    {
        [$company, $department] = $this->createCompanyContext('Vendor Statement Validation');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        $this->actingAs($owner)
            ->getJson(route('vendors.statement.export.csv', [
                'vendor' => $vendor->id,
                'from' => '2026/03/01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from']);

        $this->actingAs($owner)
            ->getJson(route('vendors.statement.print', [
                'vendor' => $vendor->id,
                'from' => '2026-03-08',
                'to' => '2026-03-01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        $this->actingAs($owner)
            ->getJson(route('vendors.statement.export.csv', [
                'vendor' => $vendor->id,
                'invoice_status' => 'not-a-valid-status',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_status']);
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+vendor-statement@example.test',
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
            'name' => 'Vendor '.Str::upper(Str::random(4)),
            'vendor_type' => 'supplier',
            'contact_person' => 'Contact Person',
            'phone' => '08000000000',
            'email' => 'vendor.statement@example.test',
            'address' => '12 Example Street',
            'bank_name' => 'Example Bank',
            'bank_code' => '058',
            'account_name' => 'Vendor Account',
            'account_number' => '12345678',
            'notes' => null,
            'is_active' => true,
        ]);
    }
}
