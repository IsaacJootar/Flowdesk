<?php

namespace Tests\Feature\Expenses;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\RequestExpenseHandoff;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseHandoffBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_eligible_settled_payout_without_creating_handoff(): void
    {
        [$company, $department] = $this->createCompanyContext('Handoff Backfill Dry Run');
        $finance = $this->createUser($company, $department);
        $request = $this->createSettledRequest($company, $department, $finance, 'FD-BACKFILL-DRY');
        $this->createSettledAttempt($company, $request, $finance);

        $this->artisan('expenses:backfill-handoffs --dry-run')
            ->expectsOutput('Eligible for handoff: 1')
            ->expectsOutput('Created handoffs: 0')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('request_expense_handoffs', [
            'request_id' => (int) $request->id,
        ]);
    }

    public function test_command_creates_missing_handoff_and_skips_existing_expenses(): void
    {
        [$company, $department] = $this->createCompanyContext('Handoff Backfill');
        $finance = $this->createUser($company, $department);
        $needsHandoff = $this->createSettledRequest($company, $department, $finance, 'FD-BACKFILL-001');
        $alreadyExpensed = $this->createSettledRequest($company, $department, $finance, 'FD-BACKFILL-002');
        $this->createSettledAttempt($company, $needsHandoff, $finance);
        $this->createSettledAttempt($company, $alreadyExpensed, $finance);
        $this->createExpense($company, $department, $finance, $alreadyExpensed);

        $this->artisan('expenses:backfill-handoffs --batch=1')
            ->expectsOutput('Eligible for handoff: 1')
            ->expectsOutput('Created handoffs: 1')
            ->expectsOutput('Already has expense: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('request_expense_handoffs', [
            'company_id' => (int) $company->id,
            'request_id' => (int) $needsHandoff->id,
            'handoff_status' => RequestExpenseHandoff::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('request_expense_handoffs', [
            'request_id' => (int) $alreadyExpensed->id,
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
            'email' => Str::slug($name).'+handoff-backfill@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => (int) $company->id,
            'name' => 'Finance',
            'code' => 'FIN',
            'is_active' => true,
        ]);

        return [$company, $department];
    }

    private function createUser(Company $company, Department $department): User
    {
        return User::factory()->create([
            'company_id' => (int) $company->id,
            'department_id' => (int) $department->id,
            'role' => UserRole::Finance->value,
            'is_active' => true,
        ]);
    }

    private function createSettledRequest(Company $company, Department $department, User $finance, string $code): SpendRequest
    {
        return SpendRequest::query()->create([
            'company_id' => (int) $company->id,
            'request_code' => $code,
            'requested_by' => (int) $finance->id,
            'department_id' => (int) $department->id,
            'title' => 'Backfill request '.$code,
            'amount' => 125000,
            'approved_amount' => 125000,
            'paid_amount' => 125000,
            'currency' => 'NGN',
            'status' => 'settled',
            'created_by' => (int) $finance->id,
            'updated_by' => (int) $finance->id,
        ]);
    }

    private function createSettledAttempt(Company $company, SpendRequest $request, User $finance): RequestPayoutExecutionAttempt
    {
        return RequestPayoutExecutionAttempt::query()->create([
            'company_id' => (int) $company->id,
            'request_id' => (int) $request->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':backfill',
            'execution_status' => 'settled',
            'amount' => 125000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAY-'.$request->request_code,
            'queued_at' => now()->subDay(),
            'processed_at' => now()->subHours(5),
            'settled_at' => now()->subHours(4),
            'attempt_count' => 1,
            'created_by' => (int) $finance->id,
            'updated_by' => (int) $finance->id,
        ]);
    }

    private function createExpense(Company $company, Department $department, User $finance, SpendRequest $request): Expense
    {
        return Expense::query()->create([
            'company_id' => (int) $company->id,
            'expense_code' => 'FD-EXP-BACKFILL',
            'request_id' => (int) $request->id,
            'department_id' => (int) $department->id,
            'title' => 'Already linked expense',
            'amount' => 125000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => (int) $finance->id,
            'created_by' => (int) $finance->id,
            'status' => 'posted',
            'is_direct' => false,
        ]);
    }
}
