<?php

namespace App\Services\Accounting\Providers;

interface AccountingProviderClient
{
    public function provider(): string;

    /**
     * @return array<int, array<string,mixed>>
     */
    public function fetchAccounts(int $companyId): array;

    public function pushEvent(int $accountingSyncEventId): string;
}
