<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementLegacyVendorBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_command_dry_run_reports_without_persisting_changes(): void
    {
        [$company, $department] = $this->createCompanyContext('Backfill Dry Run Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $vendor = $this->createVendor($company, 'Dry Run Vendor A');
        $otherVendor = $this->createVendor($company, 'Dry Run Vendor B');

        $order = $this->createPurchaseOrder($company, $vendor, $owner, 'PO-DRY-001', 125000);
        $invoice = $this->createVendorInvoice($company, $vendor, $owner, 'INV-PO-DRY-001', 125000);
        $payment = $this->createVendorPayment($company, $otherVendor, $invoice, $owner, 50000, 'DRY-PAY-001');

        $this->artisan('procurement:backfill-vendor-links --company='.$company->id.' --dry-run')
            ->assertExitCode(0);

        $invoice->refresh();
        $payment->refresh();

        $this->assertNull($invoice->purchase_order_id);
        $this->assertSame((int) $otherVendor->id, (int) $payment->vendor_id);

        $this->assertDatabaseMissing('invoice_match_results', [
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'vendor_invoice_id' => $invoice->id,
        ]);

        $this->assertFalse(TenantAuditEvent::query()
            ->where('company_id', $company->id)
            ->where('action', 'tenant.procurement.backfill.vendor_invoice_linked')
            ->exists());
    }

    public function test_backfill_command_links_invoice_recomputes_match_and_syncs_payment_vendor_scope(): void
    {
        [$company, $department] = $this->createCompanyContext('Backfill Apply Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $vendor = $this->createVendor($company, 'Apply Vendor A');
        $otherVendor = $this->createVendor($company, 'Apply Vendor B');

        $order = $this->createPurchaseOrder($company, $vendor, $owner, 'PO-APPLY-001', 250000);
        $invoice = $this->createVendorInvoice($company, $vendor, $owner, 'INV-PO-APPLY-001', 250000);
        $payment = $this->createVendorPayment($company, $otherVendor, $invoice, $owner, 90000, 'APPLY-PAY-001');

        $this->artisan('procurement:backfill-vendor-links --company='.$company->id)
            ->assertExitCode(0);

        $invoice->refresh();
        $payment->refresh();

        $this->assertSame((int) $order->id, (int) $invoice->purchase_order_id);
        $this->assertSame((int) $vendor->id, (int) $payment->vendor_id);

        $matchResult = InvoiceMatchResult::query()
            ->where('company_id', $company->id)
            ->where('purchase_order_id', $order->id)
            ->where('vendor_invoice_id', $invoice->id)
            ->first();

        $this->assertNotNull($matchResult);
        $this->assertSame(InvoiceMatchResult::STATUS_MISMATCH, (string) $matchResult?->match_status);

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $company->id)
            ->where('action', 'tenant.procurement.backfill.vendor_invoice_linked')
            ->where('entity_id', $invoice->id)
            ->exists());

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $company->id)
            ->where('action', 'tenant.procurement.backfill.vendor_payment_synced')
            ->where('entity_id', $payment->id)
            ->exists());
    }

    public function test_backfill_command_skips_ambiguous_candidates_for_safety(): void
    {
        [$company, $department] = $this->createCompanyContext('Backfill Ambiguous Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $vendor = $this->createVendor($company, 'Ambiguous Vendor');

        $this->createPurchaseOrder($company, $vendor, $owner, 'PO-AMB-001', 100000, now()->subDays(2));
        $this->createPurchaseOrder($company, $vendor, $owner, 'PO-AMB-002', 100000, now()->subDays(1));

        $invoice = $this->createVendorInvoice($company, $vendor, $owner, 'INV-AMB-001', 100000);

        $this->artisan('procurement:backfill-vendor-links --company='.$company->id)
            ->assertExitCode(0);

        $invoice->refresh();

        $this->assertNull($invoice->purchase_order_id);

        $this->assertFalse(TenantAuditEvent::query()
            ->where('company_id', $company->id)
            ->where('action', 'tenant.procurement.backfill.vendor_invoice_linked')
            ->where('entity_id', $invoice->id)
            ->exists());
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+backfill@example.test',
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

    private function createVendor(Company $company, string $name): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'vendor_type' => 'service',
            'is_active' => true,
        ]);
    }

    private function createPurchaseOrder(
        Company $company,
        Vendor $vendor,
        User $actor,
        string $poNumber,
        int $amount,
        ?\DateTimeInterface $issuedAt = null,
    ): PurchaseOrder {
        return PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => $poNumber,
            'po_status' => PurchaseOrder::STATUS_ISSUED,
            'currency_code' => 'NGN',
            'subtotal_amount' => $amount,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'issued_at' => $issuedAt ?? now()->subDay(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function createVendorInvoice(Company $company, Vendor $vendor, User $actor, string $invoiceNumber, int $amount): VendorInvoice
    {
        return VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'currency' => 'NGN',
            'total_amount' => $amount,
            'paid_amount' => 0,
            'outstanding_amount' => $amount,
            'status' => VendorInvoice::STATUS_UNPAID,
            'description' => 'Legacy invoice pending procurement linkage',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function createVendorPayment(
        Company $company,
        Vendor $vendor,
        VendorInvoice $invoice,
        User $actor,
        int $amount,
        string $reference,
    ): VendorInvoicePayment {
        return VendorInvoicePayment::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'vendor_invoice_id' => $invoice->id,
            'payment_reference' => $reference,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
