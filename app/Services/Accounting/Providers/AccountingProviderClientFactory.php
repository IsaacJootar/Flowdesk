<?php

namespace App\Services\Accounting\Providers;

use App\Enums\AccountingProvider;

class AccountingProviderClientFactory
{
    public function make(string $provider): AccountingProviderClient
    {
        $provider = AccountingProvider::normalize($provider);

        return new NullAccountingProviderClient($provider);
    }
}
