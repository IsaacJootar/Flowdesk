<?php

namespace Tests\Feature\Operations;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\RequestExpenseHandoff;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Operations\ExpenseHandoffPage;
use App\Models\User;
use App\Services\ExpenseHandoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseHandoffPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settled_payout_prepares_handoff_and_finance_can_create_linked_expense(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Handoff Tenant');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $request = $this->createSettledRequest($company, $department, $finance);
        $attempt = $this->createSettledAttempt($company, $request, $finance);

        $handoff = app(ExpenseHandoffService::class)->prepareForSettledPayout($attempt, (int) $finance->id);

        $this->assertInstanceOf(RequestExpenseHandoff::class, $handoff);
        $this->assertDatabaseHas('request_expense_handoffs', [
            'company_id' => (int) $company->id,
            'request_id' => (int) $request->id,
            'handoff_status' => RequestExpenseHandoff::STATUS_PENDING,
        ]);

        $this->actingAs($finance)
            ->get(route('operations.expense-handoff'))
            ->assertOk()
            ->assertSee('Settled Payouts Needing Expense Records');

        Livewire::actingAs($finance)
            ->test(ExpenseHandoffPage::class)
            ->call('loadData')
            ->assertSee('FD-HANDOFF-001')
            ->call('createExpense', (int) $handoff->id)
            ->assertSee('Linked expense');

        $this->assertDatabaseHas('expenses', [
            'company_id' => (int) $company->id,
            'request_id' => (int) $request->id,
            'amount' => 250000,
            'is_direct' => false,
            'payment_method' => 'transfer',
        ]);
        $this->assertDatabaseHas('request_expense_handoffs', [
            'id' => (int) $handoff->id,
            'handoff_status' => RequestExpenseHandoff::STATUS_EXPENSE_CREATED,
        ]);
        $this->assertSame(250000, (int) $request->fresh()->paid_amount);
    }

    public function test_finance_can_mark_handoff_not_required_with_reason(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Handoff Not Required');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $request = $this->createSettledRequest($company, $department, $finance);
        $attempt = $this->createSettledAttempt($company, $request, $finance);
        $handoff = app(ExpenseHandoffService::class)->prepareForSettledPayout($attempt, (int) $finance->id);

        Livewire::actingAs($finance)
            ->test(ExpenseHandoffPage::class)
            ->set('notRequiredReasons.'.(int) $handoff->id, 'Internal settlement was already captured in another ledger.')
            ->call('markNotRequired', (int) $handoff->id)
            ->assertSee('Handoff marked not required.');

        $this->assertDatabaseMissing('expenses', [
            'request_id' => (int) $request->id,
        ]);
        $this->assertDatabaseHas('request_expense_handoffs', [
            'id' => (int) $handoff->id,
            'handoff_status' => RequestExpenseHandoff::STATUS_NOT_REQUIRED,
            'resolution_reason' => 'Internal settlement was already captured in another ledger.',
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
            'email' => Str::slug($name).'+handoff@example.test',
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

    private function createSettledRequest(Company $company, Department $department, User $finance): SpendRequest
    {
        return SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-HANDOFF-001',
            'requested_by' => $finance->id,
            'department_id' => $department->id,
            'title' => 'Settled payout handoff',
            'description' => 'Created for handoff test.',
            'amount' => 250000,
            'approved_amount' => 250000,
            'currency' => 'NGN',
            'status' => 'settled',
            'submitted_at' => now()->subDays(2),
            'decided_at' => now()->subDay(),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);
    }

    private function createSettledAttempt(Company $company, SpendRequest $request, User $finance): RequestPayoutExecutionAttempt
    {
        return RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':handoff',
            'execution_status' => 'settled',
            'amount' => 250000,
            'currency_code' => 'NGN',
            'provider_reference' => 'PAY-HANDOFF-001',
            'queued_at' => now()->subDay(),
            'processed_at' => now()->subHours(4),
            'settled_at' => now()->subHours(3),
            'attempt_count' => 1,
            'metadata' => ['request_code' => (string) $request->request_code],
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);
    }
}
