<?php

namespace App\Services;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Models\User;
use App\Support\CorrelationContext;

class TenantAuditLogger
{
    public function __construct(
        private readonly CorrelationContext $correlationContext,
    ) {
    }

    /**
     * Write tenant admin actions to a dedicated platform audit stream.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        int $companyId,
        string $action,
        ?User $actor = null,
        ?string $description = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = [],
    ): TenantAuditEvent {
        // Audit rows become much more useful during incident response when the
        // same correlation ID is present in request logs, queue logs, and audit.
        if ($this->correlationContext->correlationId() !== null && ! isset($metadata['correlation_id'])) {
            $metadata['correlation_id'] = $this->correlationContext->correlationId();
        }

        return TenantAuditEvent::query()->create([
            'company_id' => $companyId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $metadata,
            'event_at' => now(),
        ]);
    }
}
