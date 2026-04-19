<?php

namespace Tests\Feature\Settings;

use App\Actions\Expenses\CreateExpense;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\CompanyExpensePolicySetting;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\SpendLifecycleControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SpendLifecycleControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_control_can_block_expense_when_department_has_no_budget(): void
    {
        [$company, $department] = $this->createCompanyContext('Spend Lifecycle Block Missing Budget');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        CompanyExpensePolicySetting::query()->create([
            'company_id' => $company->id,
            'action_policies' => CompanyExpensePolicySetting::defaultActionPolicies(),
            'metadata' => [
                'spend_lifecycle' => [
                    'budget_control_mode' => SpendLifecycleControlService::BUDGET_BLOCK_MISSING,
                    'expense_handoff_mode' => SpendLifecycleControlService::HANDOFF_FINANCE_REVIEW,
                    'direct_expense_receipt_mode' => SpendLifecycleControlService::RECEIPT_OPTIONAL,
                    'direct_expense_receipt_threshold' => 0,
                    'direct_expense_reason_required' => false,
                    'finance_override_requires_reason' => true,
                ],
            ],
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        try {
            app(CreateExpense::class)($finance, [
                'department_id' => (int) $department->id,
                'vendor_id' => null,
                'title' => 'Internal spend without budget',
                'description' => null,
                'amount' => 50000,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'accounting_category_key' => 'petty_cash',
                'paid_by_user_id' => (int) $finance->id,
                'is_direct' => true,
            ]);

            $this->fail('Expected missing-budget lifecycle control to block expense creation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('no active budget', (string) collect($exception->errors())->flatten()->first());
        }

        $this->assertDatabaseMissing('expenses', [
            'company_id' => (int) $company->id,
            'title' => 'Internal spend without budget',
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
            'email' => Str::slug($name).'+lifecycle@example.test',
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
