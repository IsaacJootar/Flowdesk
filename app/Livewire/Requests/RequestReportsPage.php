<?php

namespace App\Livewire\Requests;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Request Reports')]
class RequestReportsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public string $departmentFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 10;

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', SpendRequest::class), 403);
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

    public function updatedTypeFilter(): void
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

    public function render(): View
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestTypes = CompanyRequestType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name']);

        $baseQuery = $this->baseQuery();
        $metrics = $this->buildMetrics(clone $baseQuery);
        $statusBreakdown = $this->statusBreakdown(clone $baseQuery);
        $topDepartments = $this->topDepartments(clone $baseQuery);

        $requests = $this->readyToLoad
            ? (clone $baseQuery)
                ->with([
                    'requester:id,name',
                    'department:id,name',
                ])
                ->latest('updated_at')
                ->latest('id')
                ->paginate($this->perPage)
            : SpendRequest::query()->whereRaw('1 = 0')->paginate($this->perPage);

        return view('livewire.requests.request-reports-page', [
            'requests' => $requests,
            'departments' => $departments,
            'requestTypes' => $requestTypes,
            'statuses' => ['draft', 'in_review', 'approved', 'rejected', 'returned'],
            'metrics' => $metrics,
            'statusBreakdown' => $statusBreakdown,
            'topDepartments' => $topDepartments,
        ]);
    }

    private function baseQuery(): Builder
    {
        $query = SpendRequest::query();
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('requester', fn (Builder $requester) => $requester->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('metadata->type', $this->typeFilter);
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('department_id', (int) $this->departmentFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // Keep all report cards and table rows on one shared filtered query for consistency.
        return $this->applyRoleScope($query);
    }

    private function applyRoleScope(Builder $query): Builder
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $role = (string) $user->role;
        if (in_array($role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        if ($role === UserRole::Manager->value) {
            // Managers see department scope plus their own submissions.
            return $query->where(function (Builder $builder) use ($user): void {
                if ($user->department_id) {
                    $builder->where('department_id', (int) $user->department_id)
                        ->orWhere('requested_by', (int) $user->id);
                } else {
                    $builder->where('requested_by', (int) $user->id);
                }
            });
        }

        return $query->where('requested_by', (int) $user->id);
    }

    /**
     * @return array{
     *   total_requests:int,
     *   total_amount:int,
     *   in_review:int,
     *   approval_rate:float,
     *   avg_decision_hours:float,
     *   overdue_steps:int,
     *   escalated_steps:int
     * }
     */
    private function buildMetrics(Builder $query): array
    {
        $requestIds = (clone $query)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $totalRequests = (clone $query)->count();
        $totalAmount = (int) ((clone $query)->sum('amount') ?? 0);
        $inReview = (clone $query)->where('status', 'in_review')->count();

        $decidedCount = (clone $query)
            ->whereIn('status', ['approved', 'rejected', 'returned'])
            ->count();
        $approvedCount = (clone $query)->where('status', 'approved')->count();
        $approvalRate = $decidedCount > 0 ? round(($approvedCount / $decidedCount) * 100, 1) : 0.0;

        $avgDecisionMinutes = $this->averageDecisionMinutes(clone $query);

        // Guard against bad historical timestamps (decided_at < submitted_at).
        $avgDecisionHours = round(max(0.0, $avgDecisionMinutes) / 60, 1);

        if ($requestIds === []) {
            return [
                'total_requests' => $totalRequests,
                'total_amount' => $totalAmount,
                'in_review' => $inReview,
                'approval_rate' => $approvalRate,
                'avg_decision_hours' => $avgDecisionHours,
                'overdue_steps' => 0,
                'escalated_steps' => 0,
            ];
        }

        $overdueSteps = RequestApproval::query()
            ->whereIn('request_id', $requestIds)
            ->where('status', 'pending')
            ->whereNull('acted_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $escalatedSteps = RequestApproval::query()
            ->whereIn('request_id', $requestIds)
            ->whereNotNull('escalated_at')
            ->count();

        return [
            'total_requests' => $totalRequests,
            'total_amount' => $totalAmount,
            'in_review' => $inReview,
            'approval_rate' => $approvalRate,
            'avg_decision_hours' => $avgDecisionHours,
            'overdue_steps' => $overdueSteps,
            'escalated_steps' => $escalatedSteps,
        ];
    }

    private function averageDecisionMinutes(Builder $query): float
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        $decisionQuery = (clone $query)
            ->whereNotNull('submitted_at')
            ->whereNotNull('decided_at');

        // Use DB-native time diff where possible to avoid loading large result sets in PHP.
        return match ($driver) {
            'mysql', 'mariadb' => (float) ($decisionQuery
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, submitted_at, decided_at)) as avg_minutes')
                ->value('avg_minutes') ?? 0),
            'sqlite' => (float) ($decisionQuery
                ->selectRaw('AVG((julianday(decided_at) - julianday(submitted_at)) * 24 * 60) as avg_minutes')
                ->value('avg_minutes') ?? 0),
            'pgsql' => (float) ($decisionQuery
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (decided_at - submitted_at)) / 60) as avg_minutes')
                ->value('avg_minutes') ?? 0),
            default => $this->averageDecisionMinutesInPhp($decisionQuery),
        };
    }

    private function averageDecisionMinutesInPhp(Builder $query): float
    {
        $rows = $query->get(['submitted_at', 'decided_at']);
        if ($rows->isEmpty()) {
            return 0.0;
        }

        $totalMinutes = $rows->reduce(
            fn (float $carry, SpendRequest $request): float => $carry + (float) $request->submitted_at?->diffInMinutes($request->decided_at),
            0.0
        );

        return $totalMinutes / max(1, $rows->count());
    }

    /**
     * @return array<string, int>
     */
    private function statusBreakdown(Builder $query): array
    {
        return (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    private function topDepartments(Builder $query): Collection
    {
        return (clone $query)
            ->selectRaw('department_id, COUNT(*) as total_requests, COALESCE(SUM(amount),0) as total_amount')
            ->with('department:id,name')
            ->groupBy('department_id')
            ->orderByDesc('total_requests')
            ->limit(5)
            ->get();
    }
}

