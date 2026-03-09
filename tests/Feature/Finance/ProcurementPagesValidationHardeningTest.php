<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Livewire\Procurement\PurchaseOrdersPage;
use App\Livewire\Procurement\PurchaseReceiptsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementPagesValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_orders_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('PO Filter Harden');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(PurchaseOrdersPage::class)
            ->set('search', '  PO-001  ')
            ->assertSet('search', 'PO-001')
            ->set('statusFilter', 'invalid-status')
            ->assertSet('statusFilter', 'all')
            ->set('statusFilter', 'ISSUED')
            ->assertSet('statusFilter', 'issued')
            ->set('perPage', 999)
            ->assertSet('perPage', 10);
    }

    public function test_purchase_receipts_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('Receipt Filter Harden');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(PurchaseReceiptsPage::class)
            ->set('search', '  RCPT-001  ')
            ->assertSet('search', 'RCPT-001')
            ->set('statusFilter', 'invalid-status')
            ->assertSet('statusFilter', 'all')
            ->set('statusFilter', 'CONFIRMED')
            ->assertSet('statusFilter', 'confirmed')
            ->set('receivedFrom', '2026/03/01')
            ->assertSet('receivedFrom', null)
            ->set('receivedTo', '2026-99-99')
            ->assertSet('receivedTo', null)
            ->set('receivedFrom', '2026-03-10')
            ->set('receivedTo', '2026-03-01')
            ->assertSet('receivedTo', null)
            ->set('perPage', 999)
            ->assertSet('perPage', 10);
    }

    public function test_submit_goods_receipt_rejects_foreign_order_item_id(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('PO Receipt Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('PO Receipt Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $vendorA = $this->createVendor($companyA);
        $vendorB = $this->createVendor($companyB);

        $orderA = $this->createPurchaseOrder($companyA, $ownerA, $vendorA, 'PO-A-001');
        $orderB = $this->createPurchaseOrder($companyB, $ownerB, $vendorB, 'PO-B-001');

        $this->createOrderItem($companyA, $orderA, 1, 3, 25000);
        $foreignItem = $this->createOrderItem($companyB, $orderB, 1, 2, 30000);

        $this->actingAs($ownerA);

        Livewire::test(PurchaseOrdersPage::class)
            ->call('openDetails', $orderA->id)
            ->set('receiptForm.received_at', now()->toDateTimeString())
            ->set('receiptForm.items.0.purchase_order_item_id', $foreignItem->id)
            ->set('receiptForm.items.0.receive_quantity', 1)
            ->set('receiptForm.items.0.received_unit_cost', 30000)
            ->call('submitGoodsReceipt')
            ->assertHasErrors(['receiptForm.items.0.purchase_order_item_id']);
    }

    public function test_link_selected_vendor_invoice_rejects_cross_tenant_invoice(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('PO Invoice Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('PO Invoice Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $vendorA = $this->createVendor($companyA);
        $vendorB = $this->createVendor($companyB);

        $orderA = $this->createPurchaseOrder($companyA, $ownerA, $vendorA, 'PO-A-100');
        $foreignInvoice = $this->createVendorInvoice($companyB, $ownerB, $vendorB, 'INV-B-100');

        $this->actingAs($ownerA);

        Livewire::test(PurchaseOrdersPage::class)
            ->call('openDetails', $orderA->id)
            ->set('selectedVendorInvoiceId', $foreignInvoice->id)
            ->call('linkSelectedVendorInvoice')
            ->assertSet('feedbackError', 'Selected invoice is invalid for this purchase order.');
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+procurement-page@example.test',
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
            'email' => Str::lower(Str::random(5)).'@vendor.test',
            'address' => '12 Example Street',
            'bank_name' => 'Example Bank',
            'bank_code' => '058',
            'account_name' => 'Vendor Account',
            'account_number' => '12345678',
            'notes' => 'Vendor seed',
            'is_active' => true,
        ]);
    }

    private function createPurchaseOrder(Company $company, User $actor, Vendor $vendor, string $poNumber): PurchaseOrder
    {
        return PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => $poNumber,
            'po_status' => PurchaseOrder::STATUS_ISSUED,
            'currency_code' => 'NGN',
            'subtotal_amount' => 100000,
            'tax_amount' => 0,
            'total_amount' => 100000,
            'issued_at' => now()->subDay(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function createOrderItem(Company $company, PurchaseOrder $order, int $lineNumber, int $quantity, int $unitPrice): PurchaseOrderItem
    {
        return PurchaseOrderItem::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'line_number' => $lineNumber,
            'item_description' => 'Line '.$lineNumber,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
            'currency_code' => 'NGN',
            'received_quantity' => 0,
            'received_total' => 0,
        ]);
    }

    private function createVendorInvoice(Company $company, User $actor, Vendor $vendor, string $invoiceNumber): VendorInvoice
    {
        return VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'outstanding_amount' => 100000,
            'status' => VendorInvoice::STATUS_UNPAID,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
