<?php

namespace App\Services;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Models\User;

class TenantAuditLogger
{
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

