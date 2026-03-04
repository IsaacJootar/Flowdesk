<?php

namespace Tests\Feature\Reports;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantPilotWaveOutcome;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Livewire\Reports\ReportsCenterPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_unified_reports_center(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_staff_cannot_open_unified_reports_center(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_manager_can_render_reports_center_component(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Manager');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        $this->actingAs($manager);

        Livewire::test(ReportsCenterPage::class)
            ->call('loadData')
            ->assertSee('Unified Reports Center')
            ->assertSee('Requests')
            ->assertSee('Posted Expenses')
            ->assertSee('Procurement Controls')
            ->assertSee('Assets')
            ->assertSee('Active Budgets');
    }

    public function test_reports_center_shows_procurement_rollout_kpis(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Procurement KPI');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $vendor = $this->createVendor($company, 'KPI Vendor');

        $orderA = $this->createPurchaseOrder($company, $vendor, $finance, 'PO-KPI-001', 100000);
        $invoiceA = $this->createInvoice($company, $vendor, $finance, 'INV-KPI-001', 100000, $orderA->id);

        $orderB = $this->createPurchaseOrder($company, $vendor, $finance, 'PO-KPI-002', 120000);
        $invoiceB = $this->createInvoice($company, $vendor, $finance, 'INV-KPI-002', 120000, $orderB->id);

        InvoiceMatchResult::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $orderA->id,
            'vendor_invoice_id' => $invoiceA->id,
            'match_status' => InvoiceMatchResult::STATUS_MATCHED,
            'match_score' => 97.5,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $mismatch = InvoiceMatchResult::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $orderB->id,
            'vendor_invoice_id' => $invoiceB->id,
            'match_status' => InvoiceMatchResult::STATUS_MISMATCH,
            'match_score' => 62.0,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        InvoiceMatchException::query()->create([
            'company_id' => $company->id,
            'invoice_match_result_id' => $mismatch->id,
            'purchase_order_id' => $orderB->id,
            'vendor_invoice_id' => $invoiceB->id,
            'exception_code' => 'amount_mismatch',
            'exception_status' => InvoiceMatchException::STATUS_OPEN,
            'severity' => InvoiceMatchException::SEVERITY_HIGH,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        ProcurementCommitment::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $orderA->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => 100000,
            'currency_code' => 'NGN',
            'effective_at' => now()->subHours(80),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $this->actingAs($finance);

        Livewire::test(ReportsCenterPage::class)
            ->call('loadData')
            ->assertSee('Procurement Controls')
            ->assertSee('Open exceptions: 1')
            ->assertSee('Match pass rate: 50.0%')
            ->assertSee('Stale commitments: 1');
    }


    public function test_reports_center_shows_rollout_decision_summary_card(): void
    {
        [$company, $department] = $this->createCompanyContext('Reports Center Rollout KPI');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantPilotWaveOutcome::query()->create([
            'company_id' => $company->id,
            'wave_label' => 'wave-1',
            'outcome' => TenantPilotWaveOutcome::OUTCOME_GO,
            'decision_at' => now()->subDays(2),
            'decided_by_user_id' => $owner->id,
        ]);

        TenantPilotWaveOutcome::query()->create([
            'company_id' => $company->id,
            'wave_label' => 'wave-2',
            'outcome' => TenantPilotWaveOutcome::OUTCOME_HOLD,
            'decision_at' => now()->subDay(),
            'decided_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(ReportsCenterPage::class)
            ->call('loadData')
            ->assertSee('Pilot Rollout Decisions')
            ->assertSee('Go: 1')
            ->assertSee('Hold: 1 | No-go: 0');
    }
    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+company@example.test',
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

    private function createVendor(Company $company, string $name): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'vendor_type' => 'service',
            'is_active' => true,
        ]);
    }

    private function createPurchaseOrder(Company $company, Vendor $vendor, User $actor, string $poNumber, int $amount): PurchaseOrder
    {
        return PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => $poNumber,
            'po_status' => PurchaseOrder::STATUS_ISSUED,
            'currency_code' => 'NGN',
            'subtotal_amount' => $amount,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'issued_at' => now()->subDay(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    private function createInvoice(Company $company, Vendor $vendor, User $actor, string $invoiceNumber, int $amount, int $purchaseOrderId): VendorInvoice
    {
        return VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => $purchaseOrderId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'currency' => 'NGN',
            'total_amount' => $amount,
            'paid_amount' => 0,
            'outstanding_amount' => $amount,
            'status' => VendorInvoice::STATUS_UNPAID,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
