<?php

namespace Tests\Feature\Execution;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Procurement\Models\CompanyProcurementControlSetting;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Treasury\Models\CompanyTreasuryControlSetting;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Execution\ExecutionOpsAlertService;
use App\Services\Execution\ExecutionOpsAutoRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutionOpsGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_service_includes_execution_and_governance_backlog_alerts(): void
    {
        $tenant = $this->createTenantCompany('Ops Alerts Tenant');

        $subscription = TenantSubscription::query()->create([
            'company_id' => $tenant->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
        ]);

        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-03',
            'idempotency_key' => 'alert-billing-001',
            'attempt_status' => 'queued',
            'amount' => 1000,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now()->subMinutes(70),
            'attempt_count' => 1,
        ]);

        TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => '2026-04',
            'idempotency_key' => 'alert-billing-002',
            'attempt_status' => 'queued',
            'amount' => 1200,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now()->subMinutes(65),
            'attempt_count' => 1,
        ]);

        ExecutionWebhookEvent::query()->create([
            'provider_key' => 'manual_ops',
            'external_event_id' => 'invalid-webhook-001',
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'event_type' => 'payout.unknown',
            'verification_status' => 'invalid',
            'processing_status' => 'failed',
            'received_at' => now()->subMinutes(5),
        ]);

        ExecutionWebhookEvent::query()->create([
            'provider_key' => 'manual_ops',
            'external_event_id' => 'invalid-webhook-002',
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'event_type' => 'payout.unknown',
            'verification_status' => 'invalid',
            'processing_status' => 'failed',
            'received_at' => now()->subMinutes(4),
        ]);

        CompanyProcurementControlSetting::query()->create([
            'company_id' => $tenant->id,
            'controls' => [
                'stale_commitment_alert_age_hours' => 24,
                'stale_commitment_alert_count_threshold' => 2,
            ],
        ]);

        ProcurementCommitment::query()->create([
            'company_id' => $tenant->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => 80000,
            'currency_code' => 'NGN',
            'effective_at' => now()->subHours(36),
        ]);

        ProcurementCommitment::query()->create([
            'company_id' => $tenant->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => 90000,
            'currency_code' => 'NGN',
            'effective_at' => now()->subHours(30),
        ]);

        CompanyTreasuryControlSetting::query()->create([
            'company_id' => $tenant->id,
            'controls' => [
                'exception_alert_age_hours' => 24,
                'reconciliation_backlog_alert_count_threshold' => 2,
            ],
        ]);

        $reconA = ReconciliationException::query()->create([
            'company_id' => $tenant->id,
            'exception_code' => 'unmatched_statement_line',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_HIGH,
            'match_stream' => ReconciliationException::STREAM_EXECUTION_PAYMENT,
        ]);

        $reconA->forceFill([
            'created_at' => now()->subHours(40),
            'updated_at' => now()->subHours(40),
        ])->saveQuietly();

        $reconB = ReconciliationException::query()->create([
            'company_id' => $tenant->id,
            'exception_code' => 'low_confidence_match',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_MEDIUM,
            'match_stream' => ReconciliationException::STREAM_EXPENSE_EVIDENCE,
        ]);

        $reconB->forceFill([
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ])->saveQuietly();

        config()->set('execution.ops_alerts.stuck_queued_older_than_minutes', 30);
        config()->set('execution.ops_alerts.stuck_queued_threshold', 2);
        config()->set('execution.ops_alerts.invalid_webhook_threshold', 2);

        $summary = app(ExecutionOpsAlertService::class)->summarizeFailures(60, 99);

        $this->assertTrue(collect($summary['alerts'])->contains(fn (array $alert): bool =>
            $alert['type'] === 'stuck_queued'
            && $alert['pipeline'] === 'billing'
            && $alert['company_id'] === $tenant->id
        ));

        $this->assertTrue(collect($summary['alerts'])->contains(fn (array $alert): bool =>
            $alert['type'] === 'invalid_webhook_spike'
            && $alert['pipeline'] === 'webhook'
            && $alert['company_id'] === $tenant->id
        ));

        $this->assertTrue(collect($summary['alerts'])->contains(fn (array $alert): bool =>
            $alert['type'] === 'stale_commitment'
            && $alert['pipeline'] === 'procurement'
            && $alert['company_id'] === $tenant->id
        ));

        $this->assertTrue(collect($summary['alerts'])->contains(fn (array $alert): bool =>
            $alert['type'] === 'reconciliation_backlog'
            && $alert['pipeline'] === 'treasury'
            && $alert['company_id'] === $tenant->id
        ));

        $emitted = app(ExecutionOpsAlertService::class)->emitWarnings(60);

        $this->assertGreaterThanOrEqual(2, count($emitted['alerts']));

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.alert.summary_emitted')
            ->where('metadata->type', 'stuck_queued')
            ->where('metadata->pipeline', 'billing')
            ->exists());

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.alert.summary_emitted')
            ->where('metadata->type', 'invalid_webhook_spike')
            ->where('metadata->pipeline', 'webhook')
            ->exists());

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.alert.summary_emitted')
            ->where('metadata->type', 'stale_commitment')
            ->where('metadata->pipeline', 'procurement')
            ->where('metadata->age_hours', 24)
            ->exists());

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.alert.summary_emitted')
            ->where('metadata->type', 'reconciliation_backlog')
            ->where('metadata->pipeline', 'treasury')
            ->where('metadata->age_hours', 24)
            ->exists());
    }

    public function test_auto_recovery_service_processes_queued_billing_and_payout_attempts(): void
    {
        $tenant = $this->createTenantCompany('Ops Recovery Tenant');

        $requester = User::factory()->create([
            'company_id' => $tenant->id,
            'role' => UserRole::Staff->value,
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $tenant->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'is_active' => true,
        ]);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $tenant->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $tenant->id,
            'request_code' => 'FD-GR-0001',
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'title' => 'Guardrail payout check',
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => 'execution_queued',
            'approved_amount' => 50000,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);

        $billingAttempt = TenantSubscriptionBillingAttempt::query()->create([
            'company_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'billing_cycle_key' => now()->format('Y-m'),
            'idempotency_key' => 'auto-recovery-billing-001',
            'attempt_status' => 'queued',
            'amount' => 18000,
            'currency_code' => 'NGN',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'queued_at' => now()->subMinutes(90),
            'attempt_count' => 1,
        ]);

        $payoutAttempt = RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $tenant->id,
            'request_id' => $request->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'manual_ops',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'auto-recovery-payout-001',
            'execution_status' => 'queued',
            'amount' => 50000,
            'currency_code' => 'NGN',
            'queued_at' => now()->subMinutes(90),
            'attempt_count' => 1,
        ]);

        config()->set('execution.ops_recovery.enabled', true);
        config()->set('execution.ops_recovery.cooldown_minutes', 0);

        $summary = app(ExecutionOpsAutoRecoveryService::class)->run(
            companyId: (int) $tenant->id,
            olderThanMinutes: 30,
            maxPerPipeline: 20,
            dryRun: false,
        );

        $billingAttempt->refresh();
        $payoutAttempt->refresh();
        $request->refresh();

        $this->assertSame('skipped', (string) $billingAttempt->attempt_status);
        $this->assertSame('skipped', (string) $payoutAttempt->execution_status);
        $this->assertSame('approved_for_execution', (string) $request->status);

        $this->assertGreaterThanOrEqual(2, (int) $summary['totals']['processed']);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $tenant->id,
            'action' => 'tenant.execution.billing.auto_recovered_queued',
            'entity_type' => TenantSubscriptionBillingAttempt::class,
            'entity_id' => $billingAttempt->id,
        ]);

        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $tenant->id,
            'action' => 'tenant.execution.payout.auto_recovered_queued',
            'entity_type' => RequestPayoutExecutionAttempt::class,
            'entity_id' => $payoutAttempt->id,
        ]);

        $summaryRows = TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.auto_recovery.run_summary')
            ->count();

        $this->assertGreaterThanOrEqual(2, $summaryRows);

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.auto_recovery.run_summary')
            ->where('metadata->pipeline', 'billing')
            ->where('metadata->provider_key', 'manual_ops')
            ->exists());

        $this->assertTrue(TenantAuditEvent::query()
            ->where('company_id', $tenant->id)
            ->where('action', 'tenant.execution.auto_recovery.run_summary')
            ->where('metadata->pipeline', 'payout')
            ->where('metadata->provider_key', 'manual_ops')
            ->exists());
    }

    private function createTenantCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }
}


