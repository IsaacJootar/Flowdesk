<?php

namespace App\Services\Accounting;

use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Enums\AccountingCategory;
use App\Enums\AccountingProvider;

class AccountMappingService
{
    public function accountCodeFor(int $companyId, ?string $categoryKey, string $provider = 'csv'): ?string
    {
        $categoryKey = AccountingCategory::normalize($categoryKey);
        if ($categoryKey === null) {
            return null;
        }

        $provider = AccountingProvider::normalize($provider);

        return ChartOfAccountMapping::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('provider', $provider)
            ->where('category_key', $categoryKey)
            ->value('account_code');
    }
}
