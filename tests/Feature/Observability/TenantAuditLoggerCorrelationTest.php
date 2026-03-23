<?php

namespace Tests\Feature\Observability;

use App\Domains\Company\Models\Company;
use App\Services\TenantAuditLogger;
use App\Support\CorrelationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantAuditLoggerCorrelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_audit_logger_attaches_correlation_id_when_available(): void
    {
        $company = Company::query()->create([
            'name' => 'Audit Correlation Tenant',
            'slug' => 'audit-correlation-'.Str::lower(Str::random(6)),
            'email' => 'audit-correlation@example.test',
            'is_active' => true,
        ]);

        app(CorrelationContext::class)->setCorrelationId('corr-audit-123');

        $event = app(TenantAuditLogger::class)->log(
            companyId: (int) $company->id,
            action: 'tenant.execution.alert.summary_emitted',
            description: 'Correlation test event.'
        );

        $this->assertSame('corr-audit-123', (string) data_get((array) ($event->metadata ?? []), 'correlation_id'));
    }
}
