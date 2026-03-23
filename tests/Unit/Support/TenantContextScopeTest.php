<?php

namespace Tests\Unit\Support;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantContextScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_scoped_models_respect_explicit_tenant_context_without_auth(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Tenant Scope A',
            'slug' => 'tenant-scope-a-'.Str::lower(Str::random(6)),
            'email' => 'tenant-scope-a@example.test',
            'is_active' => true,
        ]);

        $companyB = Company::query()->create([
            'name' => 'Tenant Scope B',
            'slug' => 'tenant-scope-b-'.Str::lower(Str::random(6)),
            'email' => 'tenant-scope-b@example.test',
            'is_active' => true,
        ]);

        Department::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Ops A',
            'code' => 'OPA',
            'is_active' => true,
        ]);

        Department::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Ops B',
            'code' => 'OPB',
            'is_active' => true,
        ]);

        $departmentNames = app(TenantContext::class)->runForCompany((int) $companyA->id, function (): array {
            return Department::query()->orderBy('name')->pluck('name')->all();
        });

        $this->assertSame(['Ops A'], $departmentNames);
    }
}
