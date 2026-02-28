<?php

namespace Tests\Feature\Settings;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantSubscription;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Livewire\Settings\TenantManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Billing Ledger');

        $this->actingAs($platformOwner)
            ->get(route('platform.tenants.show', $internalCompany))
            ->assertForbidden();
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
