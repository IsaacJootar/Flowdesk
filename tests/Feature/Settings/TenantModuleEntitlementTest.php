<?php

namespace Tests\Feature\Settings;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantSubscription;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\NavAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantModuleEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_vendor_module_is_hidden_and_blocked(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Vendors');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'vendors_enabled' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $items = app(NavAccessService::class)->forUser($owner)['items'];
        $routes = array_column($items, 'route');

        $this->assertNotContains('vendors.index', $routes);

        $this->actingAs($owner)
            ->get(route('vendors.index'))
            ->assertForbidden();
    }

    public function test_disabled_reports_module_blocks_reports_routes(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Reports');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'reports_enabled' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('requests.reports'))
            ->assertForbidden();
    }

    public function test_requests_communications_requires_both_requests_and_communications_modules(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Communications');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'communications_enabled' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('requests.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('requests.communications'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('requests.communications-help'))
            ->assertForbidden();
    }

    public function test_plan_defaults_apply_when_entitlements_row_is_missing(): void
    {
        [$company, $department] = $this->createCompanyContext('Plan Defaults Missing Entitlements');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'pilot',
            'subscription_status' => 'current',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        // Pilot default matrix keeps requests enabled, but vendors disabled.
        $this->actingAs($owner)
            ->get(route('requests.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('vendors.index'))
            ->assertForbidden();
    }

    public function test_disabled_procurement_module_is_hidden_and_blocked(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Procurement');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $items = app(NavAccessService::class)->forUser($owner)['items'];
        $routes = array_column($items, 'route');

        $this->assertNotContains('procurement.release-desk', $routes);

        $this->actingAs($owner)
            ->get(route('procurement.release-desk'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('procurement.release-help'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('procurement.orders'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('procurement.receipts'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('procurement.match-exceptions'))
            ->assertForbidden();
    }

    public function test_enabled_procurement_module_is_visible_and_accessible(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Procurement Enabled');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'procurement_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $items = app(NavAccessService::class)->forUser($owner)['items'];
        $routes = array_column($items, 'route');

        $this->assertContains('procurement.release-desk', $routes);
        $this->assertNotContains('procurement.orders', $routes);
        $this->assertNotContains('procurement.receipts', $routes);

        $this->actingAs($owner)
            ->get(route('procurement.release-desk'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('procurement.release-help'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('procurement.orders'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('procurement.receipts'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('procurement.match-exceptions'))
            ->assertOk();
    }

    public function test_disabled_treasury_module_is_hidden_and_blocked(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Treasury');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'treasury_enabled' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $items = app(NavAccessService::class)->forUser($owner)['items'];
        $routes = array_column($items, 'route');

        $this->assertNotContains('treasury.reconciliation', $routes);
        $this->assertNotContains('treasury.reconciliation-exceptions', $routes);
        $this->assertNotContains('treasury.payment-runs', $routes);
        $this->assertNotContains('treasury.cash-position', $routes);

        $this->actingAs($owner)
            ->get(route('treasury.reconciliation'))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('treasury.reconciliation-help'))
            ->assertForbidden();


        $this->actingAs($owner)
            ->get(route('treasury.reconciliation-exceptions'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('treasury.payment-runs'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('treasury.cash-position'))
            ->assertForbidden();
    }

    public function test_enabled_treasury_module_is_visible_and_accessible(): void
    {
        [$company, $department] = $this->createCompanyContext('Entitlement Treasury Enabled');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'treasury_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $items = app(NavAccessService::class)->forUser($owner)['items'];
        $routes = array_column($items, 'route');

        // Treasury navigation is intentionally consolidated to one sidebar entry.
        $this->assertContains('treasury.reconciliation', $routes);
        $this->assertNotContains('treasury.reconciliation-exceptions', $routes);
        $this->assertNotContains('treasury.payment-runs', $routes);
        $this->assertNotContains('treasury.cash-position', $routes);

        $this->actingAs($owner)
            ->get(route('treasury.reconciliation'))
            ->assertOk();
        $this->actingAs($owner)
            ->get(route('treasury.reconciliation-help'))
            ->assertOk();


        $this->actingAs($owner)
            ->get(route('treasury.reconciliation-exceptions'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('treasury.payment-runs'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('treasury.cash-position'))
            ->assertOk();
    }


    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+company@example.test',
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
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

