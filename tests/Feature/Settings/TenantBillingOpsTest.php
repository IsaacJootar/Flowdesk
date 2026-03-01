<?php

namespace Tests\Feature\Settings;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantSubscription;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Settings\TenantManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TenantBillingOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_change_writes_history_and_audit_event(): void
    {
        $platformOwner = $this->createPlatformOwner();
        $company = $this->createTenantCompany('Plan History Tenant');
        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'pilot',
            'subscription_status' => 'current',
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);
        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        $this->actingAs($platformOwner);

        $component = Livewire::test(TenantManagementPage::class)
            ->call('loadData')
            ->call('openEditModal', $company->id)
            ->set('subscriptionForm.plan_code', 'growth')
            ->set('subscriptionForm.seat_limit', '25')
            ->set('entitlementsForm.vendors_enabled', false)
            ->call('saveTenant');

        $this->assertNull($component->get('feedbackError'));

        $this->assertDatabaseHas('tenant_plan_change_histories', [
            'company_id' => $company->id,
            'tenant_subscription_id' => $subscription->id,
            'previous_plan_code' => 'pilot',
            'new_plan_code' => 'growth',
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.plan.changed',
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.seat_limit.updated',
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.entitlements.updated',
        ]);
    }

    public function test_manual_payment_creates_ledger_and_unapplied_allocation(): void
    {
        $platformOwner = $this->createPlatformOwner();
        $company = $this->createTenantCompany('Payment Tenant');
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'business',
            'subscription_status' => 'current',
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        $this->actingAs($platformOwner);

        Livewire::test(TenantManagementPage::class)
            ->call('loadData')
            ->call('openPaymentModal', $company->id)
            ->set('paymentForm.amount', '150000')
            ->set('paymentForm.currency_code', 'NGN')
            ->set('paymentForm.payment_method', 'offline_transfer')
            ->set('paymentForm.reference', 'PAY-001')
            ->set('paymentForm.received_at', now()->format('Y-m-d\TH:i'))
            ->set('paymentForm.period_start', '')
            ->set('paymentForm.period_end', '')
            ->call('saveManualPayment');

        $this->assertDatabaseHas('tenant_billing_ledger_entries', [
            'company_id' => $company->id,
            'entry_type' => 'payment',
            'direction' => 'credit',
            'currency_code' => 'NGN',
        ]);
        $this->assertDatabaseHas('tenant_billing_allocations', [
            'company_id' => $company->id,
            'allocation_status' => 'unapplied',
            'currency_code' => 'NGN',
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company->id,
            'action' => 'tenant.billing.payment_recorded',
        ]);
    }

    public function test_platform_can_open_tenant_details_and_internal_company_is_blocked(): void
    {
        $platformOwner = $this->createPlatformOwner();
        $externalCompany = $this->createTenantCompany('Details Tenant');
        $internalSlug = (array) config('platform.internal_company_slugs', ['sivon-limited']);
        $internalCompany = $this->createTenantCompany('Internal Tenant', $internalSlug[0] ?? 'sivon-limited');

        $this->actingAs($platformOwner)
            ->get(route('platform.tenants.show', $externalCompany))
            ->assertOk()
            ->assertSee('Tenant Identity');

        $this->actingAs($platformOwner)
            ->get(route('platform.tenants.billing', $externalCompany))
            ->assertOk()
            ->assertSee('Billing Ledger');

        $this->actingAs($platformOwner)
            ->get(route('platform.tenants.show', $internalCompany))
            ->assertForbidden();
    }

    public function test_billing_automation_transitions_current_grace_overdue_suspended(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');
        config([
            'platform.billing_default_grace_days' => 2,
            'platform.billing_auto_suspend_after_days_overdue' => 4,
        ]);

        $platformOwner = $this->createPlatformOwner();
        $company = $this->createTenantCompany('Automation Tenant');
        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-03-10',
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        $service = app(\App\Services\TenantBillingAutomationService::class);

        // Coverage still valid on end date.
        $service->evaluateCompany($company, $platformOwner);
        $this->assertSame('current', $subscription->fresh()->subscription_status);

        // Coverage expired but still inside grace window.
        $subscription->forceFill([
            'ends_at' => '2026-03-08',
            'grace_until' => '2026-03-11',
            'subscription_status' => 'current',
        ])->save();
        $service->evaluateCompany($company, $platformOwner);
        $this->assertSame('grace', $subscription->fresh()->subscription_status);

        // Past grace but before auto-suspend threshold.
        $subscription->forceFill([
            'grace_until' => '2026-03-07',
            'subscription_status' => 'grace',
        ])->save();
        $service->evaluateCompany($company, $platformOwner);
        $this->assertSame('overdue', $subscription->fresh()->subscription_status);

        // Past grace and beyond auto-suspend threshold.
        $subscription->forceFill([
            'grace_until' => '2026-03-05',
            'subscription_status' => 'overdue',
        ])->save();
        $service->evaluateCompany($company, $platformOwner);
        $this->assertSame('suspended', $subscription->fresh()->subscription_status);

        Carbon::setTestNow();
    }

    public function test_manual_payment_period_extends_coverage_and_restores_current_status(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');

        $platformOwner = $this->createPlatformOwner();
        $company = $this->createTenantCompany('Coverage Sync Tenant');
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'business',
            'subscription_status' => 'overdue',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-03-01',
            'grace_until' => '2026-03-03',
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        $this->actingAs($platformOwner);

        Livewire::test(TenantManagementPage::class)
            ->call('loadData')
            ->call('openPaymentModal', $company->id)
            ->set('paymentForm.amount', '75000')
            ->set('paymentForm.currency_code', 'NGN')
            ->set('paymentForm.payment_method', 'offline_transfer')
            ->set('paymentForm.reference', 'PAY-COVERAGE-001')
            ->set('paymentForm.received_at', now()->format('Y-m-d\TH:i'))
            ->set('paymentForm.period_start', '2026-03-10')
            ->set('paymentForm.period_end', '2026-04-09')
            ->call('saveManualPayment');

        $subscription = TenantSubscription::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('2026-04-09', optional($subscription->ends_at)->toDateString());
        $this->assertSame('current', (string) $subscription->subscription_status);

        Carbon::setTestNow();
    }


    public function test_execution_mode_guardrail_requires_current_billing_status(): void
    {
        $platformOwner = $this->createPlatformOwner();
        $company = $this->createTenantCompany('Execution Guardrail Tenant');
        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'overdue',
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);
        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'expenses_enabled' => true,
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        $this->actingAs($platformOwner);

        Livewire::test(TenantManagementPage::class)
            ->call('loadData')
            ->call('openEditModal', $company->id)
            ->set('subscriptionForm.subscription_status', 'overdue')
            ->set('subscriptionForm.payment_execution_mode', 'execution_enabled')
            ->set('subscriptionForm.execution_provider', 'manual_ops')
            ->set('subscriptionForm.execution_allowed_channels', ['bank_transfer'])
            ->call('saveTenant')
            ->assertHasErrors(['subscriptionForm.payment_execution_mode']);

        $this->assertDatabaseHas('tenant_subscriptions', [
            'company_id' => $company->id,
            'payment_execution_mode' => 'decision_only',
        ]);
    }

    public function test_execution_mode_guardrail_requires_default_payment_authorization_workflow(): void
    {
        $platformOwner = $this->createPlatformOwner();
        $company = $this->createTenantCompany('Execution Policy Workflow Tenant');

        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'expenses_enabled' => true,
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        $this->actingAs($platformOwner);

        Livewire::test(TenantManagementPage::class)
            ->call('loadData')
            ->call('openEditModal', $company->id)
            ->set('subscriptionForm.subscription_status', 'current')
            ->set('subscriptionForm.payment_execution_mode', 'execution_enabled')
            ->set('subscriptionForm.execution_provider', 'manual_ops')
            ->set('subscriptionForm.execution_allowed_channels', ['bank_transfer'])
            ->call('saveTenant')
            ->assertHasErrors(['subscriptionForm.payment_execution_mode']);

        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Default Payment Authorization',
            'code' => 'default_payment_authorization',
            'applies_to' => ApprovalWorkflow::APPLIES_TO_PAYMENT_AUTHORIZATION,
            'description' => 'Execution authorization chain',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $platformOwner->id,
            'updated_by' => $platformOwner->id,
        ]);

        ApprovalWorkflowStep::query()->create([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_key' => 'finance_payment_authorization',
            'actor_type' => 'role',
            'actor_value' => UserRole::Finance->value,
            'is_active' => true,
        ]);

        Livewire::test(TenantManagementPage::class)
            ->call('loadData')
            ->call('openEditModal', $company->id)
            ->set('subscriptionForm.subscription_status', 'current')
            ->set('subscriptionForm.payment_execution_mode', 'execution_enabled')
            ->set('subscriptionForm.execution_provider', 'manual_ops')
            ->set('subscriptionForm.execution_allowed_channels', ['bank_transfer'])
            ->call('saveTenant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_subscriptions', [
            'company_id' => $company->id,
            'payment_execution_mode' => 'execution_enabled',
        ]);
    }
    private function createPlatformOwner(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'department_id' => null,
            'role' => UserRole::Owner->value,
            'platform_role' => PlatformUserRole::PlatformOwner->value,
            'is_active' => true,
        ]);
    }

    private function createTenantCompany(string $name, ?string $slug = null): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => $slug ?: Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+tenant@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
        ]);
    }
}

