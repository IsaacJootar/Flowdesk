<?php

namespace App\Services\Accounting\Providers;

class NullAccountingProviderClient implements AccountingProviderClient
{
    public function __construct(
        private readonly string $providerName,
    ) {
    }

    public function provider(): string
    {
        return $this->providerName;
    }

    public function fetchAccounts(int $companyId): array
    {
        return [];
    }

    public function pushEvent(int $accountingSyncEventId): string
    {
        return 'Provider API connection is not enabled yet.';
    }
}
