<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\CompanyTreasuryControlSetting;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Enums\UserRole;
use App\Livewire\Treasury\TreasuryReconciliationPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TreasuryDailyReconciliationDeskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_daily_desk_and_help_page_when_module_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Desk Owner');
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
    }

    public function test_staff_cannot_open_daily_desk_or_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Desk Staff');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'treasury_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($staff)
            ->get(route('treasury.reconciliation'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('treasury.reconciliation-help'))
            ->assertForbidden();
    }

    public function test_owner_can_resolve_exception_from_daily_desk(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Desk Resolve');
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

        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => 'Ops Account',
            'bank_name' => 'Flowdesk Bank',
            'account_reference' => 'FD-OPS-1',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $statement = BankStatement::query()->create([
            'company_id' => $company->id,
            'bank_account_id' => $account->id,
            'statement_reference' => 'STMT-TEST-001',
            'statement_date' => now()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'import_status' => 'imported',
            'imported_at' => now(),
            'imported_by_user_id' => $owner->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $line = BankStatementLine::query()->create([
            'company_id' => $company->id,
            'bank_statement_id' => $statement->id,
            'bank_account_id' => $account->id,
            'line_reference' => 'LINE-001',
            'posted_at' => now(),
            'direction' => BankStatementLine::DIRECTION_DEBIT,
            'amount' => 50000,
            'currency_code' => 'NGN',
            'source_hash' => 'line-001-hash',
            'is_reconciled' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $exception = ReconciliationException::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => $line->id,
            'exception_code' => 'unmatched_statement_line',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_HIGH,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => 'Verify payout references and retry.',
            'details' => 'Desk resolve test',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(TreasuryReconciliationPage::class)
            ->set('selectedStatementId', (int) $statement->id)
            ->call('openResolutionModal', (int) $exception->id, 'resolved')
            ->set('resolutionNotes', 'Cleared after validating source payment.')
            ->call('applyResolution')
            ->assertSet('feedbackError', null)
            ->assertSet('feedbackMessage', 'Treasury exception updated.');

        $this->assertDatabaseHas('reconciliation_exceptions', [
            'id' => (int) $exception->id,
            'exception_status' => ReconciliationException::STATUS_RESOLVED,
            'resolved_by_user_id' => (int) $owner->id,
        ]);

        $this->assertDatabaseHas('bank_statement_lines', [
            'id' => (int) $line->id,
            'is_reconciled' => true,
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
            'email' => Str::slug($name).'+treasury-desk@example.test',
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
