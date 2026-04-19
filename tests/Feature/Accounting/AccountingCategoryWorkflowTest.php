<?php

namespace Tests\Feature\Accounting;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Requests\SubmitSpendRequest;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\AccountingCategory;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingCategoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_expense_requires_spend_type_before_posting(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Direct Missing');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        try {
            app(CreateExpense::class)($finance, $this->expensePayload($department));
            $this->fail('Expected spend type validation to block direct expense posting.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounting_category_key', $exception->errors());
        }
    }

    public function test_direct_expense_stores_spend_type(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Direct Stored');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendMaintenance->value,
        ]);

        $this->assertSame(AccountingCategory::SpendMaintenance->value, (string) $expense->accounting_category_key);
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'accounting_category_key' => AccountingCategory::SpendMaintenance->value,
        ]);
    }

    public function test_request_submission_requires_spend_type(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Request Missing');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);
        $workflow = $this->createWorkflow($company, $owner);
        $request = $this->createRequest($company, $department, $staff, $workflow);

        $this->actingAs($staff);

        try {
            app(SubmitSpendRequest::class)($staff, $request, null);
            $this->fail('Expected spend type validation to block request submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounting_category_key', $exception->errors());
        }
    }

    public function test_request_linked_expense_inherits_request_spend_type(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Request Linked');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $request = $this->createRequest($company, $department, $finance, null, [
            'status' => 'approved',
            'accounting_category_key' => AccountingCategory::SpendSoftware->value,
        ]);

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'is_direct' => false,
            'request_id' => (int) $request->id,
        ]);

        $this->assertFalse((bool) $expense->is_direct);
        $this->assertSame(AccountingCategory::SpendSoftware->value, (string) $expense->accounting_category_key);
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+accounting@example.test',
            'is_active' => true,
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

    private function createWorkflow(Company $company, User $owner): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Default Request Workflow',
            'code' => 'default_request_'.Str::lower(Str::random(4)),
            'applies_to' => 'request',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        ApprovalWorkflowStep::query()->create([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_key' => 'owner_review',
            'actor_type' => 'role',
            'actor_value' => UserRole::Owner->value,
            'notification_channels' => ['in_app'],
            'is_active' => true,
        ]);

        return $workflow;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRequest(
        Company $company,
        Department $department,
        User $requester,
        ?ApprovalWorkflow $workflow,
        array $overrides = []
    ): SpendRequest {
        return SpendRequest::query()->create(array_merge([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'workflow_id' => $workflow?->id,
            'title' => 'Accounting category request',
            'description' => 'Request used to test Spend Type workflow.',
            'amount' => 85000,
            'currency' => 'NGN',
            'status' => 'draft',
            'paid_amount' => 0,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
                'request_type_name' => 'Spend',
            ],
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Department $department): array
    {
        return [
            'department_id' => (int) $department->id,
            'vendor_id' => null,
            'title' => 'Generator service payment',
            'description' => 'Maintenance work completed.',
            'amount' => 85000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => null,
        ];
    }
}
