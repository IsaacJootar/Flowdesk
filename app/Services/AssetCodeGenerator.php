<?php

namespace App\Services;

use App\Domains\Assets\Models\Asset;

class AssetCodeGenerator
{
    public function generateForCompany(int $companyId): string
    {
        // Sequence is company-scoped; bypass tenant scope to preserve deterministic numbering.
        $latestCode = Asset::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('asset_code', 'like', 'FD-AST-%')
            ->latest('id')
            ->value('asset_code');

        $nextNumber = 1;
        if (is_string($latestCode) && str_starts_with($latestCode, 'FD-AST-')) {
            $nextNumber = ((int) substr($latestCode, 7)) + 1;
        }

        return 'FD-AST-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

