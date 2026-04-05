<?php

namespace App\Services;

use App\Domains\Requests\Models\SpendRequest;

/**
 * Service for generating unique request codes for companies.
 * Generates codes in the format FD-REQ-XXXXXX where XXXXXX is a sequential number.
 */
class RequestCodeGenerator
{
    /**
     * Generate a new request code for the given company.
     * The code is company-scoped and sequential.
     */
    public function generateForCompany(int $companyId): string
    {
        // Read latest code per tenant, bypassing global scope to avoid accidental cross-context misses.
        $latestCode = SpendRequest::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('request_code', 'like', 'FD-REQ-%')
            ->latest('id')
            ->value('request_code');

        // Determine the next number in the sequence
        $nextNumber = 1;

        if (is_string($latestCode) && str_starts_with($latestCode, 'FD-REQ-')) {
            $sequence = (int) substr($latestCode, 7);
            $nextNumber = $sequence + 1;
        }

        // Format the code with leading zeros
        return 'FD-REQ-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
