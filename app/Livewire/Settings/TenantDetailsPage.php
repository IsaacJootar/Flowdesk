<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantBillingAllocation;
use App\Domains\Company\Models\TenantBillingLedgerEntry;
use App\Domains\Company\Models\TenantPlanChangeHistory;
use App\Domains\Company\Models\TenantUsageCounter;
use App\Services\PlatformAccessService;
use App\Services\TenantUsageSnapshotService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tenant Details')]
class TenantDetailsPage extends Component
{
    use WithPagination;

    public Company $company;

    public bool $readyToLoad = false;

    public string $allocationStatusFilter = 'all';

    public int $ledgerPerPage = 10;

    public int $allocationPerPage = 10;

    public int $auditPerPage = 10;

    public function mount(Company $company): void
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($company);
        $this->company = $company;
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
        app(TenantUsageSnapshotService::class)->capture((int) $this->company->id, auth()->user());
    }

    public function updatedAllocationStatusFilter(): void
    {
        if (! in_array($this->allocationStatusFilter, ['all', 'allocated', 'unapplied', 'reversed'], true)) {
            $this->allocationStatusFilter = 'all';
        }

        $this->resetPage('allocationPage');
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($this->company);

        $subscription = $this->company->subscription()->first();
        $latestUsage = $this->company->usageCounters()->latest('snapshot_at')->first();

        $stats = [
            'balance' => $this->readyToLoad ? $this->billingBalance((int) $this->company->id) : 0.0,
            'unapplied' => $this->readyToLoad
                ? (float) TenantBillingAllocation::query()
                    ->where('company_id', (int) $this->company->id)
                    ->where('allocation_status', 'unapplied')
                    ->sum('amount')
                : 0.0,
            'seat_limit' => (int) ($subscription?->seat_limit ?? 0),
            'active_users' => $this->readyToLoad
                ? (int) $this->company->users()->where('is_active', true)->count()
                : 0,
            'seat_utilization' => (float) ($latestUsage?->seat_utilization_percent ?? 0),
            'warning_level' => (string) ($latestUsage?->warning_level ?? 'normal'),
        ];

        $ledgerEntries = $this->readyToLoad
            ? TenantBillingLedgerEntry::query()
                ->where('company_id', (int) $this->company->id)
                ->latest('effective_date')
                ->latest('id')
                ->paginate($this->ledgerPerPage, ['*'], 'ledgerPage')
            : $this->emptyPaginator($this->ledgerPerPage, 'ledgerPage');

        $allocations = $this->readyToLoad
            ? TenantBillingAllocation::query()
                ->with(['manualPayment:id,reference,payment_method,received_at', 'creator:id,name'])
                ->where('company_id', (int) $this->company->id)
                ->when(
                    $this->allocationStatusFilter !== 'all',
                    fn (Builder $query) => $query->where('allocation_status', $this->allocationStatusFilter)
                )
                ->latest('id')
                ->paginate($this->allocationPerPage, ['*'], 'allocationPage')
            : $this->emptyPaginator($this->allocationPerPage, 'allocationPage');

        $planHistory = $this->readyToLoad
            ? TenantPlanChangeHistory::query()
                ->with(['changer:id,name'])
                ->where('company_id', (int) $this->company->id)
                ->latest('changed_at')
                ->limit(20)
                ->get()
            : collect();

        $usageSnapshots = $this->readyToLoad
            ? TenantUsageCounter::query()
                ->where('company_id', (int) $this->company->id)
                ->latest('snapshot_at')
                ->limit(20)
                ->get()
            : collect();

        $auditEvents = $this->readyToLoad
            ? TenantAuditEvent::query()
                ->with(['actor:id,name'])
                ->where('company_id', (int) $this->company->id)
                ->latest('event_at')
                ->paginate($this->auditPerPage, ['*'], 'auditPage')
            : $this->emptyPaginator($this->auditPerPage, 'auditPage');

        return view('livewire.settings.tenant-details-page', [
            'subscription' => $subscription,
            'stats' => $stats,
            'ledgerEntries' => $ledgerEntries,
            'allocations' => $allocations,
            'planHistory' => $planHistory,
            'usageSnapshots' => $usageSnapshots,
            'auditEvents' => $auditEvents,
        ]);
    }

    private function billingBalance(int $companyId): float
    {
        $credit = (float) TenantBillingLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('direction', 'credit')
            ->sum('amount');

        $debit = (float) TenantBillingLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('direction', 'debit')
            ->sum('amount');

        return round($credit - $debit, 2);
    }

    private function emptyPaginator(int $perPage, string $pageName)
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => $pageName,
        ]);
    }

    private function authorizePlatformOperator(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();
    }

    private function assertTenantIsExternal(Company $company): void
    {
        $internalSlugs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            (array) config('platform.internal_company_slugs', [])
        ))));

        if (in_array(strtolower((string) $company->slug), $internalSlugs, true)) {
            throw new AuthorizationException('Internal platform company is not managed from tenant details.');
        }
    }
}

