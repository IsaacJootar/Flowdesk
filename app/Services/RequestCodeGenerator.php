<?php

namespace App\Services;

use App\Domains\Requests\Models\SpendRequest;

class RequestCodeGenerator
{
    public function generateForCompany(int $companyId): string
    {
        $latestCode = SpendRequest::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('request_code', 'like', 'FD-REQ-%')
            ->latest('id')
            ->value('request_code');

        $nextNumber = 1;

        if (is_string($latestCode) && str_starts_with($latestCode, 'FD-REQ-')) {
            $sequence = (int) substr($latestCode, 7);
            $nextNumber = $sequence + 1;
        }

        return 'FD-REQ-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}

