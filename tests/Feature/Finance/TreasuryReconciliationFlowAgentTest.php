<?php

namespace Tests\Feature\Finance;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Enums\UserRole;
use App\Livewire\Treasury\TreasuryReconciliationExceptionsPage;
use App\Livewire\Treasury\TreasuryReconciliationPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TreasuryReconciliationFlowAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_agent_can_analyze_reconciliation_exception_when_ai_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Flow Agent Enabled');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->setEntitlements($company, $finance, aiEnabled: true, treasuryEnabled: true);

        [$line, $attempt] = $this->createStatementLineAndPayoutAttempt($company, $finance, 'FLOW-REF-ENABLED');
        $exception = $this->createOpenException($company, $finance, $line, $attempt, ReconciliationException::SEVERITY_HIGH);

        $this->actingAs($finance);

        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->call('loadData')
            ->assertSee('Use Flow Agent')
            ->call('analyzeExceptionWithFlowAgent', (int) $exception->id)
            ->assertSet('feedbackError', null)
            ->assertSet('flowAgentInsights.'.(int) $exception->id.'.suggested_match_type', 'payout');

        Livewire::test(TreasuryReconciliationPage::class)
            ->call('loadData')
            ->assertSee('Use Flow Agent')
            ->call('analyzeOpenExceptionWithFlowAgent', (int) $exception->id)
            ->assertSet('feedbackError', null)
            ->assertSet('flowAgentInsights.'.(int) $exception->id.'.risk_level', 'high');

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.treasury.reconciliation.exception.flow_agent_analyzed',
            'entity_type' => ReconciliationException::class,
            'entity_id' => (int) $exception->id,
        ]);
    }

    public function test_flow_agent_analysis_is_blocked_when_ai_entitlement_is_disabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Treasury Flow Agent Disabled');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->setEntitlements($company, $finance, aiEnabled: false, treasuryEnabled: true);

        [$line, $attempt] = $this->createStatementLineAndPayoutAttempt($company, $finance, 'FLOW-REF-DISABLED');
        $exception = $this->createOpenException($company, $finance, $line, $attempt, ReconciliationException::SEVERITY_MEDIUM);

        $this->actingAs($finance);

        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->call('loadData')
            ->assertDontSee('Use Flow Agent')
            ->call('analyzeExceptionWithFlowAgent', (int) $exception->id)
            ->assertSet('feedbackError', 'Flow Agent is not enabled for this tenant.');
    }

    public function test_flow_agent_analysis_remains_strictly_tenant_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Treasury Flow Agent Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Treasury Flow Agent Scope B');

        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);

        $this->setEntitlements($companyA, $financeA, aiEnabled: true, treasuryEnabled: true);
        $this->setEntitlements($companyB, $financeB, aiEnabled: true, treasuryEnabled: true);

        [$lineB, $attemptB] = $this->createStatementLineAndPayoutAttempt($companyB, $financeB, 'FLOW-REF-SCOPE');
        $foreignException = $this->createOpenException($companyB, $financeB, $lineB, $attemptB, ReconciliationException::SEVERITY_HIGH);

        $this->actingAs($financeA);

        Livewire::test(TreasuryReconciliationExceptionsPage::class)
            ->call('loadData')
            ->call('analyzeExceptionWithFlowAgent', (int) $foreignException->id)
            ->assertSet('feedbackError', 'Selected exception is no longer available in your tenant scope.');

        $this->assertDatabaseMissing('tenant_audit_events', [
            'company_id' => $companyA->id,
            'action' => 'tenant.treasury.reconciliation.exception.flow_agent_analyzed',
            'entity_id' => (int) $foreignException->id,
        ]);
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+treasury-flow-agent@example.test',
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

    private function setEntitlements(Company $company, User $actor, bool $aiEnabled, bool $treasuryEnabled): void
    {
        TenantFeatureEntitlement::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'ai_enabled' => $aiEnabled,
                'treasury_enabled' => $treasuryEnabled,
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]
        );
    }

    /**
     * @return array{0:BankStatementLine,1:RequestPayoutExecutionAttempt}
     */
    private function createStatementLineAndPayoutAttempt(Company $company, User $actor, string $reference): array
    {
        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => 'Operations Account',
            'bank_name' => 'Flowdesk Bank',
            'account_reference' => 'FD-OPS-'.Str::upper(Str::random(4)),
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $statement = BankStatement::query()->create([
            'company_id' => $company->id,
            'bank_account_id' => (int) $account->id,
            'statement_reference' => 'STMT-'.Str::upper(Str::random(6)),
            'statement_date' => now()->toDateString(),
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
            'import_status' => 'imported',
            'imported_at' => now(),
            'imported_by_user_id' => (int) $actor->id,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $line = BankStatementLine::query()->create([
            'company_id' => $company->id,
            'bank_statement_id' => (int) $statement->id,
            'bank_account_id' => (int) $account->id,
            'line_reference' => $reference,
            'description' => 'Settlement line '.$reference,
            'posted_at' => now()->subHours(4),
            'value_date' => now()->toDateString(),
            'direction' => BankStatementLine::DIRECTION_DEBIT,
            'amount' => 85000,
            'currency_code' => 'NGN',
            'source_hash' => Str::lower(Str::random(20)),
            'is_reconciled' => false,
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $attempt = RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => (int) SpendRequest::query()->create([
                'company_id' => $company->id,
                'request_code' => 'REQ-'.Str::upper(Str::random(8)),
                'requested_by' => (int) $actor->id,
                'department_id' => (int) $actor->department_id,
                'title' => 'Treasury flow agent payout',
                'amount' => 85000,
                'approved_amount' => 85000,
                'currency' => 'NGN',
                'status' => 'settled',
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ])->id,
            'tenant_subscription_id' => null,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'attempt:'.$reference,
            'execution_status' => 'settled',
            'amount' => 85000,
            'currency_code' => 'NGN',
            'provider_reference' => $reference,
            'attempt_count' => 1,
            'queued_at' => now()->subHours(6),
            'processed_at' => now()->subHours(5),
            'settled_at' => now()->subHours(4),
            'metadata' => ['request_code' => 'REQ-'.$reference],
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        return [$line, $attempt];
    }

    private function createOpenException(
        Company $company,
        User $actor,
        BankStatementLine $line,
        RequestPayoutExecutionAttempt $attempt,
        string $severity,
    ): ReconciliationException {
        $exception = ReconciliationException::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => (int) $line->id,
            'exception_code' => 'conflict_multiple_targets',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => $severity,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
            'next_action' => 'Review top candidate records and close with note.',
            'details' => 'Multiple candidate targets detected for statement line.',
            'metadata' => [
                'candidate_preview' => [
                    [
                        'target_type' => RequestPayoutExecutionAttempt::class,
                        'target_id' => (int) $attempt->id,
                        'stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
                        'confidence' => 91,
                        'reason' => 'Reference and amount aligned with payout attempt.',
                    ],
                ],
            ],
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);

        $exception->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        return $exception;
    }
}
