<?php

namespace Tests\Feature\Finance;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Requests\Models\RequestItem;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestsPage;
use App\Models\User;
use App\Services\Procurement\PurchaseOrderIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementRequestToPoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_convert_approved_request_to_po_and_issue_with_commitment(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Convert Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        DepartmentBudget::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_amount' => 2500000,
            'used_amount' => 0,
            'remaining_amount' => 2500000,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-PROC-REQ-001',
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'title' => 'Procurement conversion test request',
            'description' => 'Approved request for conversion test',
            'amount' => 600000,
            'approved_amount' => 600000,
            'currency' => 'NGN',
            'status' => 'approved',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        RequestItem::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'item_name' => 'Laptop Asset',
            'description' => 'Procurement line item',
            'quantity' => 2,
            'unit_cost' => 300000,
            'line_total' => 600000,
            'vendor_id' => $vendor->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->call('openViewModal', $request->id)
            ->call('convertSelectedRequestToPurchaseOrder')
            ->assertSet('feedbackError', null);

        $po = PurchaseOrder::query()->where('spend_request_id', $request->id)->first();

        $this->assertNotNull($po);
        $this->assertSame('draft', (string) $po->po_status);
        $this->assertSame(600000, (int) $po->total_amount);

        $issued = app(PurchaseOrderIssuanceService::class)->issue($owner, $po, 'Feature test issue');

        $this->assertSame(PurchaseOrder::STATUS_ISSUED, (string) $issued->po_status);
        $this->assertDatabaseHas('procurement_commitments', [
            'company_id' => $company->id,
            'purchase_order_id' => $po->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => 600000,
        ]);
    }

    public function test_convert_to_po_shows_error_when_vendor_is_missing(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Missing Vendor');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-PROC-REQ-002',
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'title' => 'Missing vendor request',
            'amount' => 200000,
            'approved_amount' => 200000,
            'currency' => 'NGN',
            'status' => 'approved',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->call('openViewModal', $request->id)
            ->call('convertSelectedRequestToPurchaseOrder')
            ->assertSet('feedbackError', 'Vendor is required before converting this request to a procurement order.');

        $this->assertDatabaseMissing('purchase_orders', [
            'company_id' => $company->id,
            'spend_request_id' => $request->id,
        ]);
    }

    public function test_procurement_routes_are_accessible_when_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Route Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('procurement.orders'))
            ->assertOk()
            ->assertSee('Procurement Orders');

        $this->actingAs($owner)
            ->get(route('procurement.receipts'))
            ->assertOk()
            ->assertSee('Procurement Receipts');

        $this->actingAs($owner)
            ->get(route('procurement.match-exceptions'))
            ->assertOk()
            ->assertSee('Procurement Match Exceptions');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+procurement@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Operations',
            'code' => 'OPS',
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
            'is_active' => true,
        ]);
    }
}