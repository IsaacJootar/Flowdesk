<?php

namespace App\Services;

use App\Domains\Audit\Models\ActivityLog;
use InvalidArgumentException;

/**
 * Service for logging user activities within the application.
 * Creates activity log entries with company and user context.
 */
class ActivityLogger
{
    /**
     * Log an activity event.
     * Automatically resolves company and user IDs if not provided.
     *
     * @param  array<string, mixed>  $metadata
     * @return ActivityLog
     */
    public function log(
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        array $metadata = [],
        ?int $companyId = null,
        ?int $userId = null
    ): ActivityLog {
        // Resolve company ID from parameter or authenticated user
        $resolvedCompanyId = $companyId ?? \Illuminate\Support\Facades\Auth::user()?->company_id;
        $resolvedUserId = $userId ?? \Illuminate\Support\Facades\Auth::id();

        if (! $resolvedCompanyId) {
            throw new InvalidArgumentException('Activity log requires company_id.');
        }

        // Gather context from the current request
        $context = array_filter([
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        // Create the activity log entry
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
