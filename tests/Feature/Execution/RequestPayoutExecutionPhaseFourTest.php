<?php

namespace Tests\Feature\Execution;

use App\Actions\Requests\DecideSpendRequest;
use App\Actions\Requests\SubmitSpendRequest;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use App\Services\Execution\SubscriptionBillingWebhookReconciliationService;
use App\Services\RequestApprovalRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fakes\Execution\FakeSubscriptionBillingAdapter;
use Tests\Fakes\Execution\FakeWebhookVerifier;
use Tests\TestCase;

class RequestPayoutExecutionPhaseFourTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_request_approval_transitions_to_payment_authorization_scope(): void
    {
        [$company, $department] = $this->createCompanyContext('Phase4 Scope Transition');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
        ]);

        $requestWorkflow = $this->createWorkflow($company, 'Request Chain', ApprovalWorkflow::APPLIES_TO_REQUEST);
        $this->createWorkflowStep($company, $requestWorkflow, 1, 'role', UserRole::Manager->value);

        $paymentWorkflow = $this->createWorkflow($company, 'Payment Auth Chain', ApprovalWorkflow::APPLIES_TO_PAYMENT_AUTHORIZATION);
        $this->createWorkflowStep($company, $paymentWorkflow, 1, 'role', UserRole::Finance->value);

        $request = $this->createDraftRequest($company, $department, $staff, $requestWorkflow, 120000);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $request, null);

        $this->actingAs($manager);
        $afterManagerApproval = app(DecideSpendRequest::class)($manager, $submitted->fresh(), [
            'action' => 'approve',
            'comment' => 'Manager approved',
        ], null);

        $this->assertSame('in_review', (string) $afterManagerApproval->status);
        $this->assertSame(RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION, (string) data_get((array) ($afterManagerApproval->metadata ?? []), 'approval_scope'));
        $this->assertSame(1, (int) $afterManagerApproval->current_approval_step);

        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $request->id,
            'scope' => RequestApprovalRouter::SCOPE_REQUEST,
            'step_order' => 1,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $request->id,
            'scope' => RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION,
            'step_order' => 1,
            'status' => 'pending',
        ]);

        $this->assertFalse(app(RequestApprovalRouter::class)->canApprove($manager, $afterManagerApproval->fresh()));
        $this->assertTrue(app(RequestApprovalRouter::class)->canApprove($finance, $afterManagerApproval->fresh()));
    }

    public function test_final_payment_authorization_queues_request_payout_attempt(): void
    {
        [$company, $department] = $this->createCompanyContext('Phase4 Queue Payout');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'manual_ops',
            'execution_allowed_channels' => ['bank_transfer'],
        ]);

        $requestWorkflow = $this->createWorkflow($company, 'Request Chain 2', ApprovalWorkflow::APPLIES_TO_REQUEST);
        $this->createWorkflowStep($company, $requestWorkflow, 1, 'role', UserRole::Manager->value);

        $paymentWorkflow = $this->createWorkflow($company, 'Payment Auth Chain 2', ApprovalWorkflow::APPLIES_TO_PAYMENT_AUTHORIZATION);
        $this->createWorkflowStep($company, $paymentWorkflow, 1, 'role', UserRole::Finance->value);

        $request = $this->createDraftRequest($company, $department, $staff, $requestWorkflow, 140000);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $request, null);

        $this->actingAs($manager);
        $afterManagerApproval = app(DecideSpendRequest::class)($manager, $submitted->fresh(), [
            'action' => 'approve',
            'comment' => 'Manager approved',
        ], null);

        $this->actingAs($finance);
        $afterFinanceApproval = app(DecideSpendRequest::class)($finance, $afterManagerApproval->fresh(), [
            'action' => 'approve',
            'comment' => 'Finance approved',
        ], null);

        $this->assertContains((string) $afterFinanceApproval->status, ['execution_queued', 'approved_for_execution', 'execution_processing']);

        $attempt = RequestPayoutExecutionAttempt::query()
            ->where('request_id', (int) $request->id)
            ->first();

        $this->assertNotNull($attempt);
        $this->assertContains((string) $attempt->execution_status, ['queued', 'processing', 'webhook_pending', 'skipped', 'settled', 'failed', 'reversed']);
        $this->assertSame('manual_ops', (string) $attempt->provider_key);
    }

    public function test_execution_webhook_falls_back_to_payout_reconciliation_when_billing_attempt_is_missing(): void
    {
        config()->set('execution.providers.fake_provider', [
            'subscription_billing_adapter' => FakeSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => FakeWebhookVerifier::class,
        ]);

        [$company, $department] = $this->createCompanyContext('Phase4 Webhook Fallback');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => 'execution_enabled',
            'execution_provider' => 'fake_provider',
            'execution_allowed_channels' => ['bank_transfer'],
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-P4-WEBHOOK-001',
            'requested_by' => $staff->id,
            'department_id' => $department->id,
            'title' => 'Webhook payout fallback',
            'amount' => 85000,
            'currency' => 'NGN',
            'status' => 'execution_processing',
            'approved_amount' => 85000,
            'metadata' => [
                'approval_scope' => RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION,
            ],
        ]);

        $attempt = RequestPayoutExecutionAttempt::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'tenant_subscription_id' => $subscription->id,
            'provider_key' => 'fake_provider',
            'execution_channel' => 'bank_transfer',
            'idempotency_key' => 'request:'.$request->id.':payout',
            'execution_status' => 'webhook_pending',
            'amount' => 85000,
            'currency_code' => 'NGN',
            'queued_at' => now(),
            'attempt_count' => 1,
            'metadata' => ['request_code' => $request->request_code],
        ]);

        $payload = json_encode([
            'event_id' => 'evt-phase4-001',
            'event_type' => 'payout.settled',
            'payout_attempt_id' => (int) $attempt->id,
            'request_id' => (int) $request->id,
            'idempotency_key' => (string) $attempt->idempotency_key,
            'status' => 'settled',
        ], JSON_THROW_ON_ERROR);

        $result = app(SubscriptionBillingWebhookReconciliationService::class)->receive(
            provider: 'fake_provider',
            headers: ['x-test' => '1'],
            body: $payload,
            signature: null,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['status']);

        $this->assertDatabaseHas('request_payout_execution_attempts', [
            'id' => $attempt->id,
            'execution_status' => 'settled',
            'last_provider_event_id' => 'evt-phase4-001',
        ]);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => 'settled',
        ]);

        $this->assertDatabaseHas('execution_webhook_events', [
            'provider_key' => 'fake_provider',
            'external_event_id' => 'evt-phase4-001',
            'request_payout_execution_attempt_id' => (int) $attempt->id,
            'processing_status' => 'processed',
            'verification_status' => 'valid',
        ]);
    }

    /**
     * @return array{0: Company, 1: \App\Domains\Company\Models\Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'email' => Str::slug($name).'@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = DB::table('departments')->insertGetId([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $departmentModel = \App\Domains\Company\Models\Department::query()->findOrFail($department);

        return [$company, $departmentModel];
    }

    private function createUser(Company $company, \App\Domains\Company\Models\Department $department, string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
            'reports_to_user_id' => null,
        ], $overrides));
    }

    private function createWorkflow(Company $company, string $name, string $appliesTo): ApprovalWorkflow
    {
        return ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'applies_to' => $appliesTo,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function createWorkflowStep(
        Company $company,
        ApprovalWorkflow $workflow,
        int $stepOrder,
        string $actorType,
        ?string $actorValue
    ): ApprovalWorkflowStep {
        return ApprovalWorkflowStep::query()->create([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'step_order' => $stepOrder,
            'step_key' => 'step_'.$stepOrder,
            'actor_type' => $actorType,
            'actor_value' => $actorValue,
            'notification_channels' => ['in_app'],
            'is_active' => true,
        ]);
    }

    private function createDraftRequest(
        Company $company,
        \App\Domains\Company\Models\Department $department,
        User $requester,
        ApprovalWorkflow $workflow,
        int $amount
    ): SpendRequest {
        return SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-P4-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'workflow_id' => $workflow->id,
            'title' => 'Execution-enabled request',
            'description' => 'Phase four flow',
            'amount' => $amount,
            'currency' => 'NGN',
            'status' => 'draft',
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
                'request_type_name' => 'Spend',
            ],
        ]);
    }
}
