<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Domains\Company\Models\TenantPilotWaveOutcome;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\Rollout\CapturePilotKpiSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public string $outcomeTenant = '';

    public string $outcomeWaveLabel = 'wave-1';

    public string $outcomeDecision = TenantPilotWaveOutcome::OUTCOME_GO;

    public string $outcomeDecisionAt = '';

    public string $outcomeNotes = '';

    public string $feedbackMessage = '';

    public string $feedbackTone = 'success';

    public int $feedbackKey = 0;

    public function mount(): void
    {
        $this->authorizePlatformOperator();

        $defaultTenantId = $this->tenantCompaniesBaseQuery()
            ->orderBy('name')
            ->value('id');

        if ($defaultTenantId) {
            $this->outcomeTenant = (string) $defaultTenantId;
        }
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

    public function recordWaveOutcome(): void
    {
        $this->authorizePlatformOperator();

        $tenantIds = $this->tenantCompanyIds();

        $validated = $this->validate([
            'outcomeTenant' => ['required', 'string', function (string $attribute, string $value, $fail) use ($tenantIds): void {
                if (! ctype_digit($value) || ! in_array((int) $value, $tenantIds, true)) {
                    $fail('Pilot wave decision must target a valid tenant.');
                }
            }],
            'outcomeWaveLabel' => ['required', 'string', 'max:40'],
            'outcomeDecision' => ['required', 'string', 'in:go,hold,no_go'],
            'outcomeDecisionAt' => ['nullable', 'date'],
            'outcomeNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = (int) $validated['outcomeTenant'];
        $decisionAt = trim((string) ($validated['outcomeDecisionAt'] ?? '')) !== ''
            ? Carbon::parse((string) $validated['outcomeDecisionAt'])->endOfDay()
            : now();

        $record = TenantPilotWaveOutcome::query()->create([
            'company_id' => $companyId,
            'wave_label' => trim((string) $validated['outcomeWaveLabel']),
            'outcome' => (string) $validated['outcomeDecision'],
            'decision_at' => $decisionAt,
            'notes' => trim((string) ($validated['outcomeNotes'] ?? '')) ?: null,
            'metadata' => [
                'source' => 'platform_pilot_rollout_page',
            ],
            'decided_by_user_id' => Auth::id(),
        ]);

        // Keep go/hold/no-go decisions in the tenant audit trail for rollout accountability.
        TenantAuditEvent::query()->create([
            'company_id' => $companyId,
            'actor_user_id' => Auth::id(),
            'action' => 'tenant.rollout.pilot_wave_outcome.recorded',
            'entity_type' => TenantPilotWaveOutcome::class,
            'entity_id' => (int) $record->id,
            'description' => 'Pilot wave rollout outcome was recorded.',
            'metadata' => [
                'wave_label' => (string) $record->wave_label,
                'outcome' => (string) $record->outcome,
                'decision_at' => (string) $record->decision_at?->toDateTimeString(),
            ],
            'event_at' => now(),
        ]);

        $tenantName = (string) $this->tenantCompaniesBaseQuery()->whereKey($companyId)->value('name');

        $this->outcomeNotes = '';
        $this->outcomeDecisionAt = '';
        $this->readyToLoad = true;

        $this->setFeedback(
            'Recorded '.$this->outcomeDisplayLabel((string) $record->outcome).' outcome for '.$tenantName.' ('.(string) $record->wave_label.').',
            'success'
        );
    }

    public function outcomeDisplayLabel(string $outcome): string
    {
        return match ($outcome) {
            TenantPilotWaveOutcome::OUTCOME_GO => 'Go',
            TenantPilotWaveOutcome::OUTCOME_HOLD => 'Hold',
            TenantPilotWaveOutcome::OUTCOME_NO_GO => 'No-go',
            default => ucfirst(str_replace('_', ' ', $outcome)),
        };
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

        $outcomeStats = $this->readyToLoad
            ? $this->outcomeStats()
            : [
                'go' => 0,
                'hold' => 0,
                'no_go' => 0,
                'total' => 0,
            ];

        $recentOutcomes = $this->readyToLoad
            ? $this->recentOutcomes()
            : collect();

        $cohortProgress = $this->readyToLoad
            ? $this->cohortProgressRows($tenantOptions)
            : collect();

        $delta = null;
        if ($this->readyToLoad && $this->tenantFilter !== 'all' && is_numeric($this->tenantFilter)) {
            $delta = $this->latestDeltaForTenant((int) $this->tenantFilter);
        }

        return view('livewire.platform.pilot-rollout-kpi-page', [
            'tenantOptions' => $tenantOptions,
            'captures' => $captures,
            'stats' => $stats,
            'delta' => $delta,
            'outcomeStats' => $outcomeStats,
            'recentOutcomes' => $recentOutcomes,
            'cohortProgress' => $cohortProgress,
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
     * @return array{go:int,hold:int,no_go:int,total:int}
     */
    private function outcomeStats(): array
    {
        $query = $this->waveOutcomesBaseQuery();

        return [
            'go' => (int) (clone $query)->where('outcome', TenantPilotWaveOutcome::OUTCOME_GO)->count(),
            'hold' => (int) (clone $query)->where('outcome', TenantPilotWaveOutcome::OUTCOME_HOLD)->count(),
            'no_go' => (int) (clone $query)->where('outcome', TenantPilotWaveOutcome::OUTCOME_NO_GO)->count(),
            'total' => (int) (clone $query)->count(),
        ];
    }

    /**
     * @return Collection<int, TenantPilotWaveOutcome>
     */
    private function recentOutcomes(): Collection
    {
        return $this->waveOutcomesBaseQuery()
            ->with(['company:id,name', 'decidedBy:id,name'])
            ->latest('decision_at')
            ->latest('id')
            ->limit(12)
            ->get();
    }

    /**
     * @param  Collection<int, mixed>  $tenantOptions
     * @return Collection<int, array<string,mixed>>
     */
    private function cohortProgressRows(Collection $tenantOptions): Collection
    {
        $tenantIds = $tenantOptions
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($tenantIds === []) {
            return collect();
        }

        // Fetch latest baseline/pilot capture markers once to avoid N+1 lookups per tenant.
        $captures = TenantPilotKpiCapture::query()
            ->whereIn('company_id', $tenantIds)
            ->whereIn('window_label', ['baseline', 'pilot'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get(['id', 'company_id', 'window_label', 'captured_at']);

        $baselineByTenant = [];
        $pilotByTenant = [];

        foreach ($captures as $capture) {
            $companyId = (int) $capture->company_id;
            $label = (string) $capture->window_label;

            if ($label === 'baseline' && ! isset($baselineByTenant[$companyId])) {
                $baselineByTenant[$companyId] = $capture;
            }

            if ($label === 'pilot' && ! isset($pilotByTenant[$companyId])) {
                $pilotByTenant[$companyId] = $capture;
            }
        }

        $outcomes = TenantPilotWaveOutcome::query()
            ->whereIn('company_id', $tenantIds)
            ->with(['decidedBy:id,name'])
            ->latest('decision_at')
            ->latest('id')
            ->get(['id', 'company_id', 'wave_label', 'outcome', 'decision_at', 'decided_by_user_id']);

        $outcomeByTenant = [];
        foreach ($outcomes as $outcome) {
            $companyId = (int) $outcome->company_id;

            if (! isset($outcomeByTenant[$companyId])) {
                $outcomeByTenant[$companyId] = $outcome;
            }
        }

        return $tenantOptions->map(function ($tenant) use ($baselineByTenant, $pilotByTenant, $outcomeByTenant): array {
            $companyId = (int) data_get($tenant, 'id');

            /** @var TenantPilotKpiCapture|null $baseline */
            $baseline = $baselineByTenant[$companyId] ?? null;
            /** @var TenantPilotKpiCapture|null $pilot */
            $pilot = $pilotByTenant[$companyId] ?? null;
            /** @var TenantPilotWaveOutcome|null $outcome */
            $outcome = $outcomeByTenant[$companyId] ?? null;

            $baselineDone = $baseline !== null;
            $pilotDone = $pilot !== null;
            $outcomeDone = $outcome !== null;

            $stage = 'Baseline pending';
            $nextAction = 'Capture baseline KPI window first.';

            if ($baselineDone && $pilotDone && $outcomeDone) {
                $stage = 'Ready for rollout';
                $nextAction = 'Complete. Execute the rollout decision for this tenant.';
            } elseif ($baselineDone && $pilotDone) {
                $stage = 'Decision pending';
                $nextAction = 'Record go, hold, or no-go outcome.';
            } elseif ($baselineDone) {
                $stage = 'Pilot capture pending';
                $nextAction = 'Capture pilot KPI window for this tenant.';
            }

            return [
                'tenant_name' => (string) data_get($tenant, 'name', '-'),
                'baseline_done' => $baselineDone,
                'pilot_done' => $pilotDone,
                'outcome_done' => $outcomeDone,
                'baseline_captured_at' => $baseline?->captured_at?->format('M d, Y H:i'),
                'pilot_captured_at' => $pilot?->captured_at?->format('M d, Y H:i'),
                'outcome_recorded_at' => $outcome?->decision_at?->format('M d, Y H:i'),
                'outcome_label' => $outcome ? $this->outcomeDisplayLabel((string) $outcome->outcome) : null,
                'outcome_wave_label' => $outcome?->wave_label,
                'decided_by' => $outcome?->decidedBy?->name,
                'stage' => $stage,
                'next_action' => $nextAction,
            ];
        });
    }

    private function waveOutcomesBaseQuery(): Builder
    {
        return TenantPilotWaveOutcome::query()
            ->whereIn('company_id', $this->tenantCompanyIds());
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
