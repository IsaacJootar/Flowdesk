<?php

namespace Tests\Feature\Accounting;

use App\Actions\Accounting\CreateAccountingSyncEvent;
use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\VoidExpense;
use App\Domains\Accounting\Models\AccountingSyncEvent;
use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\AccountingCategory;
use App\Enums\AccountingSyncStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ExpenseHandoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingSyncEventWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_expense_creates_pending_accounting_event_when_mapping_exists(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Mapped');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::SpendMaintenance->value, '5200');

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendMaintenance->value,
        ]);

        $this->assertDatabaseHas('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'event_type' => 'expense_posted',
            'category_key' => AccountingCategory::SpendMaintenance->value,
            'debit_account_code' => '5200',
            'status' => AccountingSyncStatus::Pending->value,
            'provider' => 'csv',
        ]);
    }

    public function test_posted_expense_is_marked_needs_mapping_when_account_code_is_missing(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Needs Mapping');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendSoftware->value,
        ]);

        $event = AccountingSyncEvent::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'expense')
            ->where('source_id', $expense->id)
            ->firstOrFail();

        $this->assertSame(AccountingSyncStatus::NeedsMapping->value, (string) $event->status);
        $this->assertSame(AccountingCategory::SpendSoftware->value, (string) $event->category_key);
        $this->assertStringContainsString('Map Software', (string) $event->last_error);
    }

    public function test_sync_event_creation_is_idempotent_for_same_source_event_and_provider(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Idempotent');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::SpendOperations->value, '5000');

        $this->actingAs($finance);

        $payload = [
            'company_id' => (int) $company->id,
            'source_type' => 'expense',
            'source_id' => 99,
            'event_type' => 'expense_posted',
            'category_key' => AccountingCategory::SpendOperations->value,
            'amount' => 12000,
            'currency_code' => 'NGN',
            'event_date' => now()->toDateString(),
            'description' => 'Manual idempotency test',
            'metadata' => ['department_id' => (int) $department->id],
        ];

        $first = app(CreateAccountingSyncEvent::class)($payload, actorUserId: (int) $finance->id);
        $second = app(CreateAccountingSyncEvent::class)([
            ...$payload,
            'amount' => 15000,
            'description' => 'Updated idempotency test',
        ], actorUserId: (int) $finance->id);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(
            1,
            AccountingSyncEvent::query()
                ->where('company_id', $company->id)
                ->where('source_type', 'expense')
                ->where('source_id', 99)
                ->where('event_type', 'expense_posted')
                ->count()
        );
        $this->assertSame(15000, (int) $second->fresh()->amount);
    }

    public function test_voiding_unexported_expense_skips_original_accounting_event(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Void Skip');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::SpendUtilities->value, '5300');

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendUtilities->value,
        ]);

        app(VoidExpense::class)($finance, $expense, ['reason' => 'Entered in error']);

        $this->assertDatabaseHas('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'event_type' => 'expense_posted',
            'status' => AccountingSyncStatus::Skipped->value,
        ]);
        $this->assertDatabaseMissing('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'event_type' => 'expense_voided',
        ]);
    }

    public function test_voiding_exported_expense_creates_reversal_event(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Void Reverse');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::SpendTraining->value, '5400');

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendTraining->value,
        ]);

        AccountingSyncEvent::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'expense')
            ->where('source_id', $expense->id)
            ->where('event_type', 'expense_posted')
            ->firstOrFail()
            ->forceFill(['status' => AccountingSyncStatus::Exported->value])
            ->save();

        app(VoidExpense::class)($finance, $expense, ['reason' => 'Supplier refunded']);

        $reversal = AccountingSyncEvent::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'expense')
            ->where('source_id', $expense->id)
            ->where('event_type', 'expense_voided')
            ->firstOrFail();

        $this->assertSame(AccountingSyncStatus::Pending->value, (string) $reversal->status);
        $this->assertSame(-1 * (int) $expense->amount, (int) $reversal->amount);
        $this->assertSame('5400', (string) $reversal->debit_account_code);
    }

    public function test_settled_payout_with_pending_handoff_creates_payout_accounting_event(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Payout');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::VendorPayment->value, '2100');
        $request = $this->createRequest($company, $department, $finance, [
            'accounting_category_key' => AccountingCategory::VendorPayment->value,
        ]);
        $attempt = $this->createSettledPayoutAttempt($company, $request, $finance);

        $this->actingAs($finance);

        app(ExpenseHandoffService::class)->prepareForSettledPayout($attempt, (int) $finance->id);

        $this->assertDatabaseHas('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'payout',
            'source_id' => $attempt->id,
            'event_type' => 'payout_completed',
            'category_key' => AccountingCategory::VendorPayment->value,
            'debit_account_code' => '2100',
            'status' => AccountingSyncStatus::Pending->value,
        ]);
    }

    public function test_linked_expense_skips_previous_payout_accounting_event(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Event Payout Skip');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::VendorPayment->value, '2100');
        $request = $this->createRequest($company, $department, $finance, [
            'accounting_category_key' => AccountingCategory::VendorPayment->value,
        ]);
        $attempt = $this->createSettledPayoutAttempt($company, $request, $finance);

        $this->actingAs($finance);

        $handoff = app(ExpenseHandoffService::class)->prepareForSettledPayout($attempt, (int) $finance->id);
        $this->assertNotNull($handoff);

        app(ExpenseHandoffService::class)->createLinkedExpense($handoff, $finance);

        $this->assertDatabaseHas('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'payout',
            'source_id' => $attempt->id,
            'event_type' => 'payout_completed',
            'status' => AccountingSyncStatus::Skipped->value,
        ]);

        $this->assertDatabaseHas('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'expense',
            'event_type' => 'expense_posted',
            'category_key' => AccountingCategory::VendorPayment->value,
            'status' => AccountingSyncStatus::Pending->value,
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
            'email' => Str::slug($name).'+events@example.test',
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

    private function mapCategory(Company $company, User $actor, string $categoryKey, string $accountCode): void
    {
        ChartOfAccountMapping::query()->create([
            'company_id' => (int) $company->id,
            'provider' => 'csv',
            'category_key' => $categoryKey,
            'account_code' => $accountCode,
            'account_name' => AccountingCategory::labelFor($categoryKey),
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRequest(Company $company, Department $department, User $requester, array $overrides = []): SpendRequest
    {
        return SpendRequest::query()->create(array_merge([
            'company_id' => (int) $company->id,
            'request_code' => 'FD-REQ-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'requested_by' => (int) $requester->id,
            'department_id' => (int) $department->id,
            'title' => 'Payout accounting request',
            'description' => 'Request used to verify payout accounting events.',
            'amount' => 85000,
            'approved_amount' => 85000,
            'currency' => 'NGN',
            'status' => 'settled',
            'paid_amount' => 0,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ], $overrides));
    }

    private function createSettledPayoutAttempt(Company $company, SpendRequest $request, User $actor): RequestPayoutExecutionAttempt
    {
        return RequestPayoutExecutionAttempt::query()->create([
            'company_id' => (int) $company->id,
            'request_id' => (int) $request->id,
            'tenant_subscription_id' => null,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':payout:test',
            'execution_status' => 'settled',
            'amount' => 85000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAYOUT-'.$request->id,
            'attempt_count' => 1,
            'queued_at' => now()->subMinutes(5),
            'processed_at' => now()->subMinutes(2),
            'settled_at' => now(),
            'metadata' => ['request_code' => (string) $request->request_code],
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Department $department): array
    {
        return [
            'department_id' => (int) $department->id,
            'vendor_id' => null,
            'title' => 'Accounting event expense',
            'description' => 'Expense used to verify accounting event creation.',
            'amount' => 85000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => null,
        ];
    }
}
