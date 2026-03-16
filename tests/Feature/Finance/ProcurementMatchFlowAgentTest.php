<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Livewire\Procurement\ProcurementMatchExceptionsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementMatchFlowAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_agent_can_analyze_procurement_match_exception_when_ai_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Flow Agent Enabled');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->setEntitlements($company, $finance, aiEnabled: true, procurementEnabled: true);

        $exception = $this->createOpenException($company, $finance, InvoiceMatchException::SEVERITY_HIGH);

        $this->actingAs($finance);

        Livewire::test(ProcurementMatchExceptionsPage::class)
            ->call('loadData')
            ->assertSee('Use Flow Agent')
            ->call('analyzeExceptionWithFlowAgent', (int) $exception->id)
            ->assertSet('feedbackError', null)
            ->assertSet('flowAgentInsights.'.(int) $exception->id.'.risk_level', 'high');

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.procurement.match.exception.flow_agent_analyzed',
            'entity_type' => InvoiceMatchException::class,
            'entity_id' => (int) $exception->id,
        ]);
    }

    public function test_flow_agent_analysis_is_blocked_when_ai_entitlement_is_disabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Procurement Flow Agent Disabled');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->setEntitlements($company, $finance, aiEnabled: false, procurementEnabled: true);

        $exception = $this->createOpenException($company, $finance, InvoiceMatchException::SEVERITY_MEDIUM);

        $this->actingAs($finance);

        Livewire::test(ProcurementMatchExceptionsPage::class)
            ->call('loadData')
            ->assertDontSee('Use Flow Agent')
            ->call('analyzeExceptionWithFlowAgent', (int) $exception->id)
            ->assertSet('feedbackError', 'Flow Agent is not enabled for this tenant.');
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+proc-match-flow@example.test',
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

    private function setEntitlements(Company $company, User $actor, bool $aiEnabled, bool $procurementEnabled): void
    {
        TenantFeatureEntitlement::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'ai_enabled' => $aiEnabled,
                'procurement_enabled' => $procurementEnabled,
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]
        );
    }

    private function createOpenException(Company $company, User $actor, string $severity): InvoiceMatchException
    {
        $vendor = Vendor::query()->create([
            'company_id' => $company->id,
            'name' => 'Vendor '.Str::upper(Str::random(4)),
            'vendor_type' => 'supplier',
            'is_active' => true,
        ]);

        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'vendor_id' => (int) $vendor->id,
            'po_number' => 'PO-FLOW-'.Str::upper(Str::random(5)),
            'po_status' => PurchaseOrder::STATUS_RECEIVED,
            'currency_code' => 'NGN',
            'subtotal_amount' => 250000,
            'tax_amount' => 0,
            'total_amount' => 250000,
            'issued_at' => now()->subDays(4),
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => (int) $vendor->id,
            'purchase_order_id' => (int) $order->id,
            'invoice_number' => 'INV-FLOW-'.Str::upper(Str::random(5)),
            'invoice_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 250000,
            'paid_amount' => 0,
            'outstanding_amount' => 250000,
            'status' => VendorInvoice::STATUS_UNPAID,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $matchResult = InvoiceMatchResult::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => (int) $order->id,
            'vendor_invoice_id' => (int) $invoice->id,
            'match_status' => InvoiceMatchResult::STATUS_MISMATCH,
            'match_score' => 35,
            'mismatch_reason' => 'no_receipt_recorded',
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $exception = InvoiceMatchException::query()->create([
            'company_id' => $company->id,
            'invoice_match_result_id' => (int) $matchResult->id,
            'purchase_order_id' => (int) $order->id,
            'vendor_invoice_id' => (int) $invoice->id,
            'exception_code' => 'no_receipt_recorded',
            'exception_status' => InvoiceMatchException::STATUS_OPEN,
            'severity' => $severity,
            'details' => 'No receipt has been posted for delivered items.',
            'metadata' => [
                'next_action' => 'Record goods receipt before rerunning match.',
            ],
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $exception->forceFill([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ])->save();

        return $exception;
    }
}
