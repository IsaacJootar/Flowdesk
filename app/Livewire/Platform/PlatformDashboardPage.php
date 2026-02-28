<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantSubscription;
use App\Enums\PlatformUserRole;
use App\Models\User;
use App\Services\PlatformAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Platform Dashboard')]
class PlatformDashboardPage extends Component
{
    public bool $readyToLoad = false;

    /** @var array{tenants:int, active:int, suspended:int, overdue:int, platform_users:int} */
    public array $stats = [
        'tenants' => 0,
        'active' => 0,
        'suspended' => 0,
        'overdue' => 0,
        'platform_users' => 0,
    ];

    public function mount(): void
    {
        $this->authorizePlatformOperator();
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
        $tenantCompanyIds = $this->tenantCompaniesBaseQuery()->pluck('id');

        $this->stats = [
            'tenants' => $this->tenantCompaniesBaseQuery()->count(),
            'active' => $this->tenantCompaniesBaseQuery()->where('lifecycle_status', 'active')->count(),
            'suspended' => $this->tenantCompaniesBaseQuery()->where('lifecycle_status', 'suspended')->count(),
            'overdue' => TenantSubscription::query()
                ->whereIn('company_id', $tenantCompanyIds)
                ->where('subscription_status', 'overdue')
                ->count(),
            'platform_users' => User::query()
                ->whereNotNull('platform_role')
                ->whereIn('platform_role', PlatformUserRole::values())
                ->count(),
        ];
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        return view('livewire.platform.platform-dashboard-page');
    }

    private function authorizePlatformOperator(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();
    }

    private function tenantCompaniesBaseQuery(): Builder
    {
        $internalSlugs = $this->internalCompanySlugs();

        return Company::query()
            ->when(
                $internalSlugs !== [],
                fn (Builder $query) => $query->whereNotIn('slug', $internalSlugs)
            );
    }

    /**
     * @return array<int, string>
     */
    private function internalCompanySlugs(): array
    {
        $slugs = (array) config('platform.internal_company_slugs', []);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            $slugs
        ))));
    }
}
