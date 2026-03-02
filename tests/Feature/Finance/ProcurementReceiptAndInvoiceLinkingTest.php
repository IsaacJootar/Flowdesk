<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Procurement\Models\CompanyProcurementControlSetting;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Procurement\CreateGoodsReceiptService;
use App\Services\Procurement\LinkVendorInvoiceToPurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcurementReceiptAndInvoiceLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_receipt_updates_line_balances_and_po_status_progression(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Receipt Tenant');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company);

        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-TEST-001',
            'po_status' => PurchaseOrder::STATUS_ISSUED,
            'currency_code' => 'NGN',
            'subtotal_amount' => 150000,
            'tax_amount' => 0,
            'total_amount' => 150000,
            'issued_at' => now()->subDay(),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $lineOne = PurchaseOrderItem::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'line_number' => 1,
            'item_description' => 'Printer toner',
            'quantity' => 10,
            'unit_price' => 10000,
            'line_total' => 100000,
            'currency_code' => 'NGN',
            'received_quantity' => 0,
            'received_total' => 0,
        ]);

        $lineTwo = PurchaseOrderItem::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'line_number' => 2,
            'item_description' => 'Paper reams',
            'quantity' => 5,
            'unit_price' => 10000,
            'line_total' => 50000,
            'currency_code' => 'NGN',
            'received_quantity' => 0,
            'received_total' => 0,
        ]);

        $service = app(CreateGoodsReceiptService::class);

        $firstReceipt = $service->create($finance, $order, [
            'received_at' => now()->toDateTimeString(),
            'notes' => 'First delivery batch',
            'items' => [
                [
                    'purchase_order_item_id' => $lineOne->id,
                    'received_quantity' => 4,
                    'received_unit_cost' => 10000,
                ],
                [
                    'purchase_order_item_id' => $lineTwo->id,
                    'received_quantity' => 5,
                    'received_unit_cost' => 10000,
                ],
            ],
        ]);

        $this->assertSame('confirmed', (string) $firstReceipt->receipt_status);

        $order->refresh();
        $lineOne->refresh();
        $lineTwo->refresh();

        $this->assertSame(PurchaseOrder::STATUS_PART_RECEIVED, (string) $order->po_status);
        $this->assertSame(4.0, (float) $lineOne->received_quantity);
        $this->assertSame(5.0, (float) $lineTwo->received_quantity);

        $service->create($finance, $order, [
            'received_at' => now()->addHour()->toDateTimeString(),
            'notes' => 'Final delivery batch',
            'items' => [
                [
                    'purchase_order_item_id' => $lineOne->id,
                    'received_quantity' => 6,
                    'received_unit_cost' => 10000,
                ],
            ],
        ]);

        $order->refresh();
        $lineOne->refresh();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, (string) $order->po_status);
        $this->assertSame(10.0, (float) $lineOne->received_quantity);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.procurement.goods_receipt.created',
        ]);

        $this->assertSame(
            2,
            TenantAuditEvent::query()
                ->where('company_id', $company->id)
                ->where('action', 'tenant.procurement.goods_receipt.created')
                ->count()
        );
    }

    public function test_over_receipt_is_blocked_by_default_control(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Over Receipt Control');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company);

        CompanyProcurementControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => [
                'conversion_allowed_statuses' => ['approved'],
                'require_vendor_on_conversion' => true,
                'default_expected_delivery_days' => 14,
                'auto_post_commitment_on_issue' => true,
                'issue_allowed_roles' => ['owner', 'finance'],
                'receipt_allowed_roles' => ['owner', 'finance'],
                'invoice_link_allowed_roles' => ['owner', 'finance'],
                'allow_over_receipt' => false,
            ],
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-TEST-002',
            'po_status' => PurchaseOrder::STATUS_ISSUED,
            'currency_code' => 'NGN',
            'subtotal_amount' => 50000,
            'tax_amount' => 0,
            'total_amount' => 50000,
            'issued_at' => now()->subDay(),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $line = PurchaseOrderItem::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'line_number' => 1,
            'item_description' => 'Network cable',
            'quantity' => 2,
            'unit_price' => 25000,
            'line_total' => 50000,
            'currency_code' => 'NGN',
            'received_quantity' => 0,
            'received_total' => 0,
        ]);

        $service = app(CreateGoodsReceiptService::class);

        $this->expectException(ValidationException::class);

        $service->create($finance, $order, [
            'received_at' => now()->toDateTimeString(),
            'items' => [
                [
                    'purchase_order_item_id' => $line->id,
                    'received_quantity' => 3,
                    'received_unit_cost' => 25000,
                ],
            ],
        ]);
    }

    public function test_vendor_invoice_can_be_linked_to_purchase_order_and_moves_status_to_invoiced(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Invoice Link Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company);

        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-TEST-003',
            'po_status' => PurchaseOrder::STATUS_RECEIVED,
            'currency_code' => 'NGN',
            'subtotal_amount' => 200000,
            'tax_amount' => 0,
            'total_amount' => 200000,
            'issued_at' => now()->subDays(2),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-LINK-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 200000,
            'paid_amount' => 0,
            'outstanding_amount' => 200000,
            'status' => VendorInvoice::STATUS_UNPAID,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $service = app(LinkVendorInvoiceToPurchaseOrderService::class);
        $updatedOrder = $service->link($owner, $order, $invoice);

        $invoice->refresh();

        $this->assertSame($order->id, (int) $invoice->purchase_order_id);
        $this->assertSame(PurchaseOrder::STATUS_INVOICED, (string) $updatedOrder->po_status);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.procurement.vendor_invoice.linked',
            'entity_id' => $invoice->id,
        ]);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.procurement.purchase_order.invoiced',
            'entity_id' => $order->id,
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