<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\Rollout\CapturePilotKpiSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Pilot Rollout KPIs')]
class PilotRolloutKpiPage extends Component
{
    use InteractsWithTenantCompanies;
    use WithPagination;

    public bool $readyToLoad = false;

    public string $tenantFilter = 'all';

    public string $windowFilter = 'all';

    public int $perPage = 15;

    public string $captureTenant = 'all';

    public string $captureWindowLabel = 'pilot';

    public string $captureWindowDays = '14';

    public string $captureDateEnd = '';

    public string $captureNotes = '';

    public string $feedbackMessage = '';

    public string $feedbackTone = 'success';

    public int $feedbackKey = 0;

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
    }

    public function updatedTenantFilter(): void
    {
        $this->resetPage('capturesPage');
    }

    public function updatedWindowFilter(): void
    {
        $this->resetPage('capturesPage');
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [15, 30, 50], true)) {
            $this->perPage = 15;
        }

        $this->resetPage('capturesPage');
    }

    public function captureNow(CapturePilotKpiSnapshotService $service): void
    {
        $this->authorizePlatformOperator();

        $validated = $this->validate([
            'captureTenant' => ['required', 'string', function (string $attribute, string $value, $fail): void {
                if ($value !== 'all' && ! ctype_digit($value)) {
                    $fail('Capture scope must be all tenants or a specific tenant.');
                }
            }],
            'captureWindowLabel' => ['required', 'string', 'max:30'],
            'captureWindowDays' => ['required', 'integer', 'min:1', 'max:90'],
            'captureDateEnd' => ['nullable', 'date'],
            'captureNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = $validated['captureTenant'] !== 'all'
            ? (int) $validated['captureTenant']
            : null;

        $windowDays = (int) $validated['captureWindowDays'];
        $windowEnd = trim((string) $validated['captureDateEnd']) !== ''
            ? Carbon::parse((string) $validated['captureDateEnd'])->endOfDay()
            : now();
        $windowStart = $windowEnd->copy()->subDays($windowDays - 1)->startOfDay();

        $summary = $service->captureWindow(
            companyId: $companyId,
            windowLabel: (string) $validated['captureWindowLabel'],
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            actor: Auth::user(),
            notes: trim((string) ($validated['captureNotes'] ?? '')) ?: null,
        );

        $this->readyToLoad = true;
        $this->resetPage('capturesPage');

        if ((int) ($summary['captured'] ?? 0) === 0) {
            $this->setFeedback('No KPI rows were captured for the selected scope/window.', 'warning');

            return;
        }

        $this->setFeedback(
            'Captured '.$summary['captured'].' tenant KPI snapshot row(s) for '.$summary['window_label'].' window.',
            'success'
        );
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        $tenantOptions = $this->tenantCompaniesBaseQuery()
            ->orderBy('name')
            ->get(['id', 'name']);

        $captures = $this->readyToLoad
            ? $this->filteredCapturesQuery()
                ->with(['company:id,name', 'capturedBy:id,name'])
                ->latest('captured_at')
                ->latest('id')
                ->paginate($this->perPage, ['*'], 'capturesPage')
            : $this->emptyPaginator($this->perPage, 'capturesPage');

        $stats = $this->readyToLoad
            ? $this->summaryStats()
            : [
                'captures' => 0,
                'tenants' => 0,
                'avg_match_pass_rate_percent' => 0.0,
                'avg_auto_reconciliation_rate_percent' => 0.0,
            ];

        $delta = null;
        if ($this->readyToLoad && $this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $delta = $this->latestDeltaForTenant((int) $this->tenantFilter);
        }

        return view('livewire.platform.pilot-rollout-kpi-page', [
            'tenantOptions' => $tenantOptions,
            'captures' => $captures,
            'stats' => $stats,
            'delta' => $delta,
        ]);
    }

    private function setFeedback(string $message, string $tone): void
    {
        $this->feedbackMessage = $message;
        $this->feedbackTone = $tone;
        $this->feedbackKey++;
    }

    private function filteredCapturesQuery(): Builder
    {
        $query = TenantPilotKpiCapture::query()
            ->whereIn('company_id', $this->tenantCompanyIds());

        if ($this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $query->where('company_id', (int) $this->tenantFilter);
        }

        if ($this->windowFilter !== 'all' && in_array($this->windowFilter, ['baseline', 'pilot', 'custom'], true)) {
            $query->where('window_label', $this->windowFilter);
        }

        return $query;
    }

    /**
     * @return array{captures:int,tenants:int,avg_match_pass_rate_percent:float,avg_auto_reconciliation_rate_percent:float}
     */
    private function summaryStats(): array
    {
        $query = $this->filteredCapturesQuery();

        return [
            'captures' => (int) (clone $query)->count(),
            'tenants' => (int) (clone $query)->distinct('company_id')->count('company_id'),
            'avg_match_pass_rate_percent' => round((float) ((clone $query)->avg('match_pass_rate_percent') ?? 0), 1),
            'avg_auto_reconciliation_rate_percent' => round((float) ((clone $query)->avg('auto_reconciliation_rate_percent') ?? 0), 1),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function latestDeltaForTenant(int $companyId): ?array
    {
        $baseline = TenantPilotKpiCapture::query()
            ->where('company_id', $companyId)
            ->where('window_label', 'baseline')
            ->latest('captured_at')
            ->latest('id')
            ->first();

        $pilot = TenantPilotKpiCapture::query()
            ->where('company_id', $companyId)
            ->where('window_label', 'pilot')
            ->latest('captured_at')
            ->latest('id')
            ->first();

        if (! $baseline || ! $pilot) {
            return null;
        }

        return [
            'baseline' => $baseline,
            'pilot' => $pilot,
            'match_pass_rate_delta' => round((float) $pilot->match_pass_rate_percent - (float) $baseline->match_pass_rate_percent, 2),
            'auto_reconciliation_rate_delta' => round((float) $pilot->auto_reconciliation_rate_percent - (float) $baseline->auto_reconciliation_rate_percent, 2),
            'open_procurement_exceptions_delta' => (int) $pilot->open_procurement_exceptions - (int) $baseline->open_procurement_exceptions,
            'open_treasury_exceptions_delta' => (int) $pilot->open_treasury_exceptions - (int) $baseline->open_treasury_exceptions,
            'incident_rate_per_week_delta' => round((float) $pilot->incident_rate_per_week - (float) $baseline->incident_rate_per_week, 2),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function tenantCompanyIds(): array
    {
        return $this->tenantCompaniesBaseQuery()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => $pageName,
        ]);
    }
}
