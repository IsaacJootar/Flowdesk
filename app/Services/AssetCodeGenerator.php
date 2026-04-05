<?php

namespace App\Services;

use App\Domains\Assets\Models\Asset;

/**
 * Service for generating unique asset codes for companies.
 * Generates codes in the format FD-AST-XXXXXX where XXXXXX is a sequential number.
 */
class AssetCodeGenerator
{
    /**
     * Generate a new asset code for the given company.
     * The code is company-scoped and sequential.
     */
    public function generateForCompany(int $companyId): string
    {
        // Sequence is company-scoped; bypass tenant scope to preserve deterministic numbering.
        $latestCode = Asset::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('asset_code', 'like', 'FD-AST-%')
            ->latest('id')
            ->value('asset_code');

        // Determine the next number in the sequence
        $nextNumber = 1;
        if (is_string($latestCode) && str_starts_with($latestCode, 'FD-AST-')) {
            $nextNumber = ((int) substr($latestCode, 7)) + 1;
        }

        // Format the code with leading zeros
        return 'FD-AST-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

