<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\CompanyTreasuryControlSetting;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Treasury\Models\ReconciliationMatch;
use App\Enums\UserRole;
use App\Livewire\Treasury\TreasuryReconciliationPage;
use App\Livewire\Treasury\TreasuryReconciliationExceptionsPage;
use App\Models\User;
use App\Services\Treasury\AutoReconcileStatementService;
use App\Services\Treasury\ImportBankStatementCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TreasuryReconciliationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_treasury_routes_are_accessible_when_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Route Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'treasury_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('treasury.reconciliation'))
            ->assertOk()
            ->assertSee('Daily Bank Reconciliation');

        $this->actingAs($owner)
            ->get(route('treasury.reconciliation-help'))
            ->assertOk()
            ->assertSee('Daily Bank Reconciliation Guide');

        $this->actingAs($owner)
            ->get(route('treasury.reconciliation-exceptions'))
            ->assertOk()
            ->assertSee('Unresolved Bank Items');

        $this->actingAs($owner)
            ->get(route('treasury.payment-runs'))
            ->assertOk()
            ->assertSee('Payment Runs');

        $this->actingAs($owner)
            ->get(route('treasury.cash-position'))
            ->assertOk()
            ->assertSee('Cash Position');
    }


    public function test_treasury_exception_action_enforces_tenant_allowed_roles(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Role Guardrail Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        CompanyTreasuryControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => [
                'exception_action_allowed_roles' => ['owner', 'finance'],
                'exception_action_requires_maker_checker' => false,
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $exception = ReconciliationException::query()->create([
            'company_id' => $company->id,
            'exception_code' => 'execution_payout_failed',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_HIGH,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => 'Review payout state and provider response.',
            'details' => 'Role guardrail test exception.',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($manager);

        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->call('openResolutionModal', (int) $exception->id, 'resolved')
            ->set('resolutionNotes', 'Manager trying to close exception.')
            ->call('applyResolution')
            ->assertSet('feedbackError', 'Only owner, finance can mark items as fixed or accept and close them.');

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => (int) $exception->id,
            'exception_status' => ReconciliationException::STATUS_OPEN,
        ]);
    }

    public function test_treasury_exception_action_enforces_maker_checker_when_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Maker Checker Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        CompanyTreasuryControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => [
                'exception_action_allowed_roles' => ['owner', 'finance'],
                'exception_action_requires_maker_checker' => true,
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $exception = ReconciliationException::query()->create([
            'company_id' => $company->id,
            'exception_code' => 'execution_billing_failed',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_HIGH,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => 'Investigate billing provider status.',
            'details' => 'Maker-checker test exception.',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);
        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->call('openResolutionModal', (int) $exception->id, 'resolved')
            ->set('resolutionNotes', 'Owner trying to self-resolve.')
            ->call('applyResolution')
            ->assertSet('feedbackError', 'A second person must confirm this decision — you cannot close an item you raised yourself.');

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => (int) $exception->id,
            'exception_status' => ReconciliationException::STATUS_OPEN,
        ]);

        $this->actingAs($finance);
        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->call('openResolutionModal', (int) $exception->id, 'resolved')
            ->set('resolutionNotes', 'Finance completed independent review.')
            ->call('applyResolution')
            ->assertSet('feedbackError', null)
            ->assertSet('feedbackMessage', 'Item marked as fixed and closed.');

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => (int) $exception->id,
            'exception_status' => ReconciliationException::STATUS_RESOLVED,
            'resolved_by_user_id' => (int) $finance->id,
        ]);
    }

    public function test_statement_import_requires_selected_bank_account_within_tenant_scope(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Treasury Import Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Treasury Import Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $foreignAccount = BankAccount::query()->create([
            'company_id' => $companyB->id,
            'account_name' => 'Foreign Account',
            'bank_name' => 'Foreign Bank',
            'account_reference' => 'FD-FOREIGN-001',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $ownerB->id,
            'updated_by' => $ownerB->id,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'invalid-scope.csv',
            implode("\n", [
                'posted_at,value_date,line_reference,description,direction,amount,currency_code,balance_after',
                now()->format('Y-m-d H:i:s').','.now()->toDateString().',SCOPE-001,Scope validation row,debit,10000,NGN,100000',
            ])
        );

        $this->actingAs($ownerA);

        Livewire::test(TreasuryReconciliationPage::class)
            ->set('selectedBankAccountId', (int) $foreignAccount->id)
            ->set('statementFile', $csv)
            ->call('importStatement')
            ->assertHasErrors(['selectedBankAccountId']);
    }

    public function test_treasury_exception_action_rejects_invalid_resolution_action_payload(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Invalid Resolution Action');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        CompanyTreasuryControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => [
                'exception_action_allowed_roles' => ['owner', 'finance'],
                'exception_action_requires_maker_checker' => false,
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $exception = ReconciliationException::query()->create([
            'company_id' => $company->id,
            'exception_code' => 'execution_invalid_action',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_MEDIUM,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => 'Inspect settlement pipeline.',
            'details' => 'Invalid resolution action hardening test.',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->set('selectedExceptionId', (int) $exception->id)
            ->set('resolutionAction', 'archive')
            ->set('resolutionNotes', 'Attempting unsupported action.')
            ->call('applyResolution')
            ->assertHasErrors(['resolutionAction']);

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => (int) $exception->id,
            'exception_status' => ReconciliationException::STATUS_OPEN,
        ]);
    }

    public function test_statement_import_and_auto_reconciliation_create_matches_and_exceptions(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Reconciliation Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => 'Operations Account',
            'bank_name' => 'Flowdesk Bank',
            'account_reference' => 'FD-OPS-001',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-TREASURY-REQ-001',
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'title' => 'Treasury reconciliation request',
            'amount' => 50000,
            'approved_amount' => 50000,
            'currency' => 'NGN',
            'status' => 'settled',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':payout',
            'execution_status' => 'settled',
            'amount' => 50000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAYOUT-REF-001',
            'queued_at' => now()->subHours(5),
            'processed_at' => now()->subHours(4),
            'settled_at' => now()->subHours(3),
            'attempt_count' => 1,
            'metadata' => ['request_code' => (string) $request->request_code],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'statement.csv',
            implode("\n", [
                'posted_at,value_date,line_reference,description,direction,amount,currency_code,balance_after',
                now()->subHours(3)->format('Y-m-d H:i:s').', '.now()->toDateString().',PAYOUT-REF-001,Vendor payout debit,debit,50000,NGN,950000',
                now()->subHours(2)->format('Y-m-d H:i:s').', '.now()->toDateString().',UNKNOWN-REF,Unmatched debit,debit,70000,NGN,880000',
            ])
        );

        $importResult = app(ImportBankStatementCsvService::class)->import($owner, (int) $account->id, $csv);

        $this->assertSame(2, (int) $importResult['imported']);
        $this->assertSame(0, (int) $importResult['skipped']);

        $summary = app(AutoReconcileStatementService::class)->run($owner, $importResult['statement']);

        $this->assertSame(1, (int) $summary['matched']);
        $this->assertSame(1, (int) $summary['exceptions']);

        $this->assertDatabaseHas('reconciliation_matches', [
            'company_id' => $company->id,
            'match_stream' => ReconciliationMatch::STREAM_EXECUTION_PAYMENT,
            'match_status' => ReconciliationMatch::STATUS_MATCHED,
        ]);

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'company_id' => $company->id,
            'exception_code' => 'unmatched_statement_line',
            'exception_status' => ReconciliationException::STATUS_OPEN,
        ]);
    }
    public function test_direct_expense_high_similarity_auto_reconciles_within_date_window(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Expense Match Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => 'Operations Account',
            'bank_name' => 'Flowdesk Bank',
            'account_reference' => 'FD-OPS-EXP-001',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $expense = Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'EXP-HEUR-001',
            'department_id' => $department->id,
            'title' => 'Ikeja Electric token purchase',
            'description' => 'Utility meter top up for office power',
            'amount' => 15000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'card',
            'created_by' => $owner->id,
            'status' => 'posted',
            'is_direct' => true,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'statement-expense-match.csv',
            implode("\n", [
                'posted_at,value_date,line_reference,description,direction,amount,currency_code,balance_after',
                now()->subHours(2)->format('Y-m-d H:i:s').', '.now()->toDateString().',CARD-UTIL-001,IKEJA ELECTRIC TOKEN PAYMENT,debit,15000,NGN,900000',
            ])
        );

        $importResult = app(ImportBankStatementCsvService::class)->import($owner, (int) $account->id, $csv);
        $summary = app(AutoReconcileStatementService::class)->run($owner, $importResult['statement']);

        $this->assertSame(1, (int) $summary['matched']);
        $this->assertSame(0, (int) $summary['exceptions']);
        $this->assertSame(0, (int) $summary['conflicts']);

        $this->assertDatabaseHas('reconciliation_matches', [
            'company_id' => $company->id,
            'match_stream' => ReconciliationMatch::STREAM_EXPENSE_EVIDENCE,
            'match_status' => ReconciliationMatch::STATUS_MATCHED,
            'match_target_type' => Expense::class,
            'match_target_id' => $expense->id,
        ]);
    }

    public function test_direct_expense_low_similarity_stays_manual_with_low_confidence_exception(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Expense Low Confidence Tenant');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => 'Operations Account',
            'bank_name' => 'Flowdesk Bank',
            'account_reference' => 'FD-OPS-EXP-002',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $expense = Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'EXP-HEUR-002',
            'department_id' => $department->id,
            'title' => 'Office lunch reimbursement',
            'description' => 'Team lunch reimbursement note',
            'amount' => 22000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'created_by' => $owner->id,
            'status' => 'posted',
            'is_direct' => true,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'statement-expense-low-confidence.csv',
            implode("\n", [
                'posted_at,value_date,line_reference,description,direction,amount,currency_code,balance_after',
                now()->subHours(1)->format('Y-m-d H:i:s').', '.now()->toDateString().',CARD-FUEL-220,FUEL STATION CARD DEBIT,debit,22000,NGN,880000',
            ])
        );

        $importResult = app(ImportBankStatementCsvService::class)->import($owner, (int) $account->id, $csv);
        $summary = app(AutoReconcileStatementService::class)->run($owner, $importResult['statement']);

        $this->assertSame(0, (int) $summary['matched']);
        $this->assertSame(1, (int) $summary['exceptions']);

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'company_id' => $company->id,
            'exception_code' => 'low_confidence_match',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'match_stream' => ReconciliationException::STREAM_EXPENSE_EVIDENCE,
        ]);

        $this->assertDatabaseMissing('reconciliation_matches', [
            'company_id' => $company->id,
            'match_target_type' => Expense::class,
            'match_target_id' => $expense->id,
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
            'email' => Str::slug($name).'+treasury@example.test',
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
}
