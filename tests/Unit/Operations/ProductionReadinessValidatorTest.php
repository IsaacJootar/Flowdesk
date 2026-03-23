<?php

namespace Tests\Unit\Operations;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantSubscription;
use App\Services\Operations\ProductionReadinessValidator;
use App\Services\TenantExecutionModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionReadinessValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_reports_blocking_and_warning_issues_for_unsafe_production_settings(): void
    {
        $this->app['config']->set('app.env', 'production');
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('queue.default', 'sync');
        $this->app['config']->set('cache.default', 'array');
        $this->app['config']->set('mail.default', 'log');
        $this->app['config']->set('session.secure', false);
        $this->app['config']->set('services.sms.provider', 'placeholder');

        $company = Company::query()->create([
            'name' => 'Validator Tenant',
            'slug' => 'validator-'.Str::lower(Str::random(6)),
            'email' => 'validator@example.test',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => 'growth',
            'subscription_status' => 'current',
            'payment_execution_mode' => TenantExecutionModeService::MODE_EXECUTION_ENABLED,
            'execution_provider' => 'manual_ops',
        ]);

        $summary = app(ProductionReadinessValidator::class)->summary();

        $this->assertGreaterThanOrEqual(5, (int) $summary['blocking']);
        $this->assertGreaterThanOrEqual(1, (int) $summary['warning']);
    }
}
