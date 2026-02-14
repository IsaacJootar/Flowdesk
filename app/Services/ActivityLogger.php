<?php

namespace App\Services;

use App\Domains\Audit\Models\ActivityLog;
use InvalidArgumentException;

class ActivityLogger
{
    public function log(
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        array $metadata = [],
        ?int $companyId = null,
        ?int $userId = null
    ): ActivityLog {
        $resolvedCompanyId = $companyId ?? auth()->user()?->company_id;
        $resolvedUserId = $userId ?? auth()->id();

        if (! $resolvedCompanyId) {
            throw new InvalidArgumentException('Activity log requires company_id.');
        }

        $context = array_filter([
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        return ActivityLog::create([
            'company_id' => $resolvedCompanyId,
            'user_id' => $resolvedUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => empty($metadata) && empty($context) ? null : array_merge($context, $metadata),
            'created_at' => now(),
        ]);
    }
}
