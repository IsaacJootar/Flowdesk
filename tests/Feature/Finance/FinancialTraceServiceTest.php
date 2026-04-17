<?php

namespace Tests\Feature\Finance;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\PaymentRun;
use App\Domains\Treasury\Models\PaymentRunItem;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Treasury\Models\ReconciliationMatch;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\FinancialTraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialTraceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_read_only_request_financial_trace(): void
    {
        [$company, $department] = $this->createCompanyContext('Financial Trace Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $vendor = $this->createVendor($company, $owner);

        $budget = DepartmentBudget::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_amount' => 1000000,
            'used_amount' => 200000,
            'remaining_amount' => 800000,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-TRACE-REQ-001',
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'title' => 'Financial trace request',
            'amount' => 300000,
            'approved_amount' => 300000,
            'paid_amount' => 300000,
            'currency' => 'NGN',
            'status' => 'settled',
            'submitted_at' => now()->subDays(4),
            'decided_at' => now()->subDays(3),
            'metadata' => [
                'policy_checks' => [
                    'budget' => [
                        'has_budget' => true,
                        'budget_id' => (int) $budget->id,
                        'allocated_amount' => 1000000,
                        'spent_amount' => 200000,
                        'projected_amount' => 500000,
                        'remaining_amount' => 800000,
                        'over_amount' => 0,
                        'is_exceeded' => false,
                        'mode' => 'warn',
                        'effective_date' => now()->toDateString(),
                    ],
                ],
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        RequestApproval::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'scope' => 'request',
            'step_order' => 1,
            'step_key' => 'finance_review',
            'status' => 'approved',
            'action' => 'approved',
            'acted_by' => $owner->id,
            'acted_at' => now()->subDays(3),
            'from_status' => 'in_review',
            'to_status' => 'approved_for_execution',
        ]);

        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'spend_request_id' => $request->id,
            'department_budget_id' => $budget->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-TRACE-001',
            'po_status' => PurchaseOrder::STATUS_ISSUED,
            'currency_code' => 'NGN',
            'subtotal_amount' => 300000,
            'tax_amount' => 0,
            'total_amount' => 300000,
            'issued_at' => now()->subDays(2),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $commitment = ProcurementCommitment::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'department_budget_id' => $budget->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => 300000,
            'currency_code' => 'NGN',
            'effective_at' => now()->subDays(2),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        InvoiceMatchResult::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $order->id,
            'match_status' => InvoiceMatchResult::STATUS_MATCHED,
            'match_score' => 98.5,
            'matched_at' => now()->subDay(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $attempt = RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':payout',
            'execution_status' => 'settled',
            'amount' => 300000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAY-TRACE-001',
            'queued_at' => now()->subDay(),
            'processed_at' => now()->subHours(20),
            'settled_at' => now()->subHours(18),
            'attempt_count' => 1,
            'metadata' => ['request_code' => $request->request_code],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $run = PaymentRun::query()->create([
            'company_id' => $company->id,
            'run_code' => 'RUN-TRACE-001',
            'run_status' => PaymentRun::STATUS_COMPLETED,
            'run_type' => 'payout',
            'processed_at' => now()->subHours(18),
            'total_items' => 1,
            'total_amount' => 300000,
            'currency_code' => 'NGN',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        PaymentRunItem::query()->create([
            'company_id' => $company->id,
            'payment_run_id' => $run->id,
            'request_payout_execution_attempt_id' => $attempt->id,
            'item_reference' => 'ITEM-TRACE-001',
            'item_status' => PaymentRunItem::STATUS_SETTLED,
            'amount' => 300000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAY-TRACE-001',
            'processed_at' => now()->subHours(18),
            'settled_at' => now()->subHours(18),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $expense = Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'EXP-TRACE-001',
            'request_id' => $request->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'title' => 'Trace linked expense',
            'amount' => 300000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => 'posted',
            'is_direct' => false,
        ]);

        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => 'Main Account',
            'bank_name' => 'Flowdesk Bank',
            'account_reference' => 'BANK-TRACE-001',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $statement = BankStatement::query()->create([
            'company_id' => $company->id,
            'bank_account_id' => $account->id,
            'statement_reference' => 'STMT-TRACE-001',
            'statement_date' => now()->toDateString(),
            'import_status' => BankStatement::STATUS_IMPORTED,
            'imported_at' => now(),
            'imported_by_user_id' => $owner->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $line = BankStatementLine::query()->create([
            'company_id' => $company->id,
            'bank_statement_id' => $statement->id,
            'bank_account_id' => $account->id,
            'line_reference' => 'PAY-TRACE-001',
            'posted_at' => now()->subHours(18),
            'description' => 'Payment PAY-TRACE-001',
            'direction' => BankStatementLine::DIRECTION_DEBIT,
            'amount' => 300000,
            'currency_code' => 'NGN',
            'is_reconciled' => true,
            'reconciled_at' => now()->subHours(17),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $match = ReconciliationMatch::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => $line->id,
            'match_target_type' => RequestPayoutExecutionAttempt::class,
            'match_target_id' => $attempt->id,
            'match_stream' => ReconciliationMatch::STREAM_EXECUTION_PAYMENT,
            'match_status' => ReconciliationMatch::STATUS_MATCHED,
            'confidence_score' => 95,
            'matched_by' => 'system',
            'matched_at' => now()->subHours(17),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        ReconciliationException::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => $line->id,
            'reconciliation_match_id' => $match->id,
            'exception_code' => 'trace_review_closed',
            'exception_status' => ReconciliationException::STATUS_RESOLVED,
            'severity' => ReconciliationException::SEVERITY_LOW,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'details' => 'Trace test exception',
            'resolved_at' => now()->subHours(16),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        ActivityLog::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'action' => 'request.submitted',
            'entity_type' => SpendRequest::class,
            'entity_id' => $request->id,
            'metadata' => ['request_code' => $request->request_code],
            'created_at' => now()->subDays(4),
        ]);

        TenantAuditEvent::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $owner->id,
            'action' => 'tenant.procurement.commitment.posted',
            'entity_type' => ProcurementCommitment::class,
            'entity_id' => $commitment->id,
            'description' => 'Budget commitment posted from purchase order issuance.',
            'metadata' => [
                'request_code' => $request->request_code,
                'purchase_order_id' => $order->id,
            ],
            'event_at' => now()->subDays(2),
        ]);

        $trace = app(FinancialTraceService::class)->buildForRequestId((int) $company->id, (int) $request->id);

        $this->assertIsArray($trace);
        $this->assertSame('FD-TRACE-REQ-001', $trace['request']['request_code']);
        $this->assertSame((int) $budget->id, $trace['budget']['budget_id']);
        $this->assertSame('within_budget', $trace['budget']['status']);
        $this->assertCount(1, $trace['approvals']);
        $this->assertSame('PO-TRACE-001', $trace['procurement']['purchase_orders'][0]['po_number']);
        $this->assertSame(300000, $trace['procurement']['summary']['active_commitment_amount']);
        $this->assertSame('settled', $trace['payment']['attempt']['status']);
        $this->assertSame('RUN-TRACE-001', $trace['payment']['payment_run_items'][0]['run_code']);
        $this->assertSame('EXP-TRACE-001', $trace['expenses'][0]['expense_code']);
        $this->assertTrue($trace['reconciliation']['summary']['has_match']);
        $this->assertSame(0, $trace['reconciliation']['summary']['open_exceptions']);
        $this->assertNotEmpty($trace['audit']['activity_logs']);
        $this->assertNotEmpty($trace['audit']['tenant_audit_events']);
        $this->assertNotEmpty($trace['timeline']);
        $this->assertSame([], $trace['gaps']);
    }

    public function test_trace_lookup_is_scoped_to_company(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Financial Trace Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Financial Trace Scope B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $request = SpendRequest::query()->create([
            'company_id' => $companyA->id,
            'request_code' => 'FD-TRACE-SCOPE-001',
            'requested_by' => $ownerA->id,
            'department_id' => $departmentA->id,
            'title' => 'Scoped request',
            'amount' => 100000,
            'currency' => 'NGN',
            'status' => 'approved',
            'created_by' => $ownerA->id,
            'updated_by' => $ownerA->id,
        ]);

        $this->assertNull(app(FinancialTraceService::class)->buildForRequestId((int) $companyB->id, (int) $request->id));
        $this->assertNotNull($ownerB);
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+trace@example.test',
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

    private function createVendor(Company $company, User $owner): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => 'Trace Vendor',
            'vendor_type' => 'supplier',
            'email' => 'trace-vendor@example.test',
            'phone' => '08000000000',
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
    }
}
