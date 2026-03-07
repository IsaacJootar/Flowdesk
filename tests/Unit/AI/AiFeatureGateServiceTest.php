<?php

namespace Tests\Unit\AI;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Services\AI\AiFeatureGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiFeatureGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_for_company_returns_false_when_no_entitlement_row_exists(): void
    {
        $company = Company::query()->create([
            'name' => 'AI Gate Missing Entitlement',
            'slug' => 'ai-gate-missing-'.Str::lower(Str::random(6)),
            'email' => 'ai-gate-missing@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $service = app(AiFeatureGateService::class);

        $this->assertFalse($service->enabledForCompany((int) $company->id));
    }

    public function test_enabled_for_company_reflects_entitlement_toggle(): void
    {
        $company = Company::query()->create([
            'name' => 'AI Gate Enabled Entitlement',
            'slug' => 'ai-gate-enabled-'.Str::lower(Str::random(6)),
            'email' => 'ai-gate-enabled@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        TenantFeatureEntitlement::query()->create([
            'company_id' => (int) $company->id,
            'ai_enabled' => true,
        ]);

        $service = app(AiFeatureGateService::class);

        $this->assertTrue($service->enabledForCompany((int) $company->id));
    }
}
