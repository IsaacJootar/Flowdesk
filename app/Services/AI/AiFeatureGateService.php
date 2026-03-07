<?php

namespace App\Services\AI;

use App\Domains\Company\Models\TenantFeatureEntitlement;

// Checks per-tenant AI entitlement before any AI feature is exposed.
class AiFeatureGateService
{
    public function enabledForCompany(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        $enabled = TenantFeatureEntitlement::query()
            ->where('company_id', $companyId)
            ->value('ai_enabled');

        return (bool) $enabled;
    }
}