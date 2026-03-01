<?php

namespace App\Livewire\Platform\Concerns;

use App\Domains\Company\Models\Company;
use App\Services\PlatformAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithTenantCompanies
{
    protected function authorizePlatformOperator(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();
    }

    protected function assertTenantIsExternal(Company $company): void
    {
        if (in_array(strtolower((string) $company->slug), $this->internalCompanySlugs(), true)) {
            throw new AuthorizationException('Internal platform company is not managed from tenant pages.');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function internalCompanySlugs(): array
    {
        $slugs = (array) config('platform.internal_company_slugs', []);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            $slugs
        ))));
    }

    protected function tenantCompaniesBaseQuery(): Builder
    {
        $internalSlugs = $this->internalCompanySlugs();

        return Company::query()
            ->when(
                $internalSlugs !== [],
                fn (Builder $query) => $query->whereNotIn('slug', $internalSlugs)
            );
    }
}
