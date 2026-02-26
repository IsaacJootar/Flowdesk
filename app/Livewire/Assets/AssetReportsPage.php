<?php

namespace App\Livewire\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetCategory;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Company\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Asset Reports')]
class AssetReportsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $categoryFilter = 'all';

    public string $assigneeFilter = 'all';

    public string $departmentFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 10;

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', Asset::class), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAssigneeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'asset_report_'.now()->format('Ymd_His').'.csv';
        $currency = strtoupper((string) (\Illuminate\Support\Facades\Auth::user()?->company?->currency_code ?: 'NGN'));

        $query = $this->baseQuery()
            ->with(['category:id,name', 'assignee:id,name', 'assignedDepartment:id,name'])
            ->withSum([
                'events as maintenance_total' => function (Builder $builder): void {
                    $builder->where('event_type', AssetEvent::TYPE_MAINTENANCE);
                    if ($this->dateFrom !== '') {
                        $builder->whereDate('event_date', '>=', $this->dateFrom);
                    }
                    if ($this->dateTo !== '') {
                        $builder->whereDate('event_date', '<=', $this->dateTo);
                    }
                },
            ], 'amount')
            ->orderBy('asset_code');

        return response()->streamDownload(function () use ($query, $currency): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, [
                'Asset Code',
                'Asset Name',
                'Category',
                'Assignee',
                'Department',
                'Status',
                'Condition',
                'Acquisition Date',
                'Maintenance Cost',
                'Currency',
            ]);

            $query->chunkById(300, function ($assets) use ($stream, $currency): void {
                foreach ($assets as $asset) {
                    fputcsv($stream, [
                        (string) $asset->asset_code,
                        (string) $asset->name,
                        (string) ($asset->category?->name ?? 'Uncategorized'),
                        (string) ($asset->assignee?->name ?? 'Unassigned'),
                        (string) ($asset->assignedDepartment?->name ?? '-'),
                        (string) $asset->status,
                        (string) $asset->condition,
                        optional($asset->acquisition_date)->toDateString() ?? '',
                        (string) ((int) ($asset->maintenance_total ?? 0)),
                        $currency,
                    ]);
                }
            }, 'id');

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $baseQuery = $this->baseQuery();
        $metrics = $this->buildMetrics(clone $baseQuery);

        $assets = $this->readyToLoad
            ? (clone $baseQuery)
                ->with(['category:id,name', 'assignee:id,name', 'assignedDepartment:id,name'])
                ->withSum([
                    'events as maintenance_total' => function (Builder $builder): void {
                        $builder->where('event_type', AssetEvent::TYPE_MAINTENANCE);
                        if ($this->dateFrom !== '') {
                            $builder->whereDate('event_date', '>=', $this->dateFrom);
                        }
                        if ($this->dateTo !== '') {
                            $builder->whereDate('event_date', '<=', $this->dateTo);
                        }
                    },
                ], 'amount')
                ->latest('created_at')
                ->latest('id')
                ->paginate($this->perPage)
            : Asset::query()->whereRaw('1 = 0')->paginate($this->perPage);

        return view('livewire.assets.asset-reports-page', [
            'assets' => $assets,
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statusOptions' => Asset::STATUSES,
            'metrics' => $metrics,
            'currencyCode' => strtoupper((string) (\Illuminate\Support\Facades\Auth::user()?->company?->currency_code ?: 'NGN')),
        ]);
    }

    private function baseQuery(): Builder
    {
        return Asset::query()
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('asset_code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('serial_number', 'like', '%'.$search.'%');
                });
            })
            ->when($this->statusFilter !== 'all', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== 'all', fn (Builder $query) => $query->where('asset_category_id', (int) $this->categoryFilter))
            ->when($this->assigneeFilter !== 'all', fn (Builder $query) => $query->where('assigned_to_user_id', (int) $this->assigneeFilter))
            ->when($this->departmentFilter !== 'all', fn (Builder $query) => $query->where('assigned_department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('acquisition_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('acquisition_date', '<=', $this->dateTo));
    }

    /**
     * @return array{
     *   total_assets:int,
     *   assigned_assets:int,
     *   unassigned_assets:int,
     *   maintenance_cost:int,
     *   disposed_assets:int
     * }
     */
    private function buildMetrics(Builder $query): array
    {
        $totalAssets = (clone $query)->count();
        $assignedAssets = (clone $query)
            ->whereNotNull('assigned_to_user_id')
            ->where('status', '!=', Asset::STATUS_DISPOSED)
            ->count();
        $unassignedAssets = (clone $query)
            ->whereNull('assigned_to_user_id')
            ->where('status', '!=', Asset::STATUS_DISPOSED)
            ->count();
        $disposedAssets = (clone $query)->where('status', Asset::STATUS_DISPOSED)->count();

        $assetIds = (clone $query)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $maintenanceQuery = AssetEvent::query()
            ->whereIn('asset_id', $assetIds)
            ->where('event_type', AssetEvent::TYPE_MAINTENANCE);
        if ($this->dateFrom !== '') {
            $maintenanceQuery->whereDate('event_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $maintenanceQuery->whereDate('event_date', '<=', $this->dateTo);
        }
        $maintenanceCost = $assetIds === [] ? 0 : (int) ($maintenanceQuery->sum('amount') ?? 0);

        return [
            'total_assets' => $totalAssets,
            'assigned_assets' => $assignedAssets,
            'unassigned_assets' => $unassignedAssets,
            'maintenance_cost' => $maintenanceCost,
            'disposed_assets' => $disposedAssets,
        ];
    }
}

