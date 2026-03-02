<?php

namespace App\Livewire\Execution;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Enums\UserRole;
use App\Services\PlatformAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Execution Health')]
class ExecutionHealthPage extends Component
{
    public bool $readyToLoad = false;

    /**
     * @var array{
     *     status_label:string,
     *     status_tone:string,
     *     last_recovery_outcome_at:string,
     *     affected_billings:int,
     *     affected_payouts:int,
     *     next_action:string,
     *     current_incident_id:?string
     * }
     */
    public array $summary = [];

    /**
     * @var array<int, array{incident_id:string,summary:string,occurred_at:string}>
     */
    public array $recentSummaries = [];

    public function mount(): void
    {
        abort_unless($this->canAccessPage(), 403);

        $this->summary = $this->emptySummary();
        $this->recentSummaries = [];
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
        $this->summary = $this->buildSummary();
        $this->recentSummaries = $this->buildRecentSummaries();
    }

    public function render(): View
    {
        return view('livewire.execution.execution-health-page', [
            'summary' => $this->summary,
            'recentSummaries' => $this->recentSummaries,
        ]);
    }

    /**
     * @return array{
     *     status_label:string,
     *     status_tone:string,
     *     last_recovery_outcome_at:string,
     *     affected_billings:int,
     *     affected_payouts:int,
     *     next_action:string,
     *     current_incident_id:?string
     * }
     */
    private function buildSummary(): array
    {
        $companyId = (int) (Auth::user()?->company_id ?? 0);
        if ($companyId <= 0) {
            return $this->emptySummary();
        }

        $windowMinutes = max(5, (int) config('execution.ops_alerts.window_minutes', 60));
        $recoveryAgeThresholdMinutes = max(1, (int) config('execution.ops_recovery.older_than_minutes', 30));
        $cutoff = Carbon::now()->subMinutes($recoveryAgeThresholdMinutes);

        // "Affected" keeps tenant messaging simple: failed records + old queued records.
        $affectedBillings = (int) TenantSubscriptionBillingAttempt::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($cutoff): void {
                $query->where('attempt_status', 'failed')
                    ->orWhere(function ($queued) use ($cutoff): void {
                        $queued->where('attempt_status', 'queued')
                            ->whereNotNull('queued_at')
                            ->where('queued_at', '<=', $cutoff);
                    });
            })
            ->count();

        $affectedPayouts = (int) RequestPayoutExecutionAttempt::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($cutoff): void {
                $query->where('execution_status', 'failed')
                    ->orWhere(function ($queued) use ($cutoff): void {
                        $queued->where('execution_status', 'queued')
                            ->whereNotNull('queued_at')
                            ->where('queued_at', '<=', $cutoff);
                    });
            })
            ->count();

        $latestAlert = TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->where('action', 'tenant.execution.alert.summary_emitted')
            ->latest('event_at')
            ->latest('id')
            ->first(['id', 'event_at']);

        $latestRecovery = $this->latestRecoveryEvent($companyId);

        $hasRecentAlert = (bool) (
            $latestAlert
            && $latestAlert->event_at
            && $latestAlert->event_at->gte(Carbon::now()->subMinutes($windowMinutes))
        );

        $statusLabel = 'Healthy';
        $statusTone = 'healthy';
        $currentIncidentId = null;
        $nextAction = 'Retry later.';

        if ($hasRecentAlert) {
            $statusLabel = 'Action needed';
            $statusTone = 'action_needed';
            $currentIncidentId = $this->formatIncidentId((int) $latestAlert->id);
            $nextAction = 'Contact support with incident ID '.$currentIncidentId.'.';
        } elseif (($affectedBillings + $affectedPayouts) > 0) {
            $statusLabel = 'Delayed';
            $statusTone = 'delayed';
            $nextAction = 'Retry later.';
        }

        return [
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'last_recovery_outcome_at' => $latestRecovery?->event_at?->format('M d, Y H:i') ?? 'No recovery outcome yet.',
            'affected_billings' => $affectedBillings,
            'affected_payouts' => $affectedPayouts,
            'next_action' => $nextAction,
            'current_incident_id' => $currentIncidentId,
        ];
    }

    /**
     * @return array<int, array{incident_id:string,summary:string,occurred_at:string}>
     */
    private function buildRecentSummaries(): array
    {
        $companyId = (int) (Auth::user()?->company_id ?? 0);
        if ($companyId <= 0) {
            return [];
        }

        $rows = TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('action', [
                'tenant.execution.alert.summary_emitted',
                'tenant.execution.auto_recovery.run_summary',
            ])
            ->latest('event_at')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'action', 'event_at', 'metadata']);

        return $rows->map(function (TenantAuditEvent $event): array {
            return [
                'incident_id' => $this->formatIncidentId((int) $event->id),
                'summary' => $this->summaryLabel((string) $event->action, (array) ($event->metadata ?? [])),
                'occurred_at' => $event->event_at?->format('M d, Y H:i') ?? '-',
            ];
        })->all();
    }

    private function canAccessPage(): bool
    {
        $user = Auth::user();
        if (! $user || app(PlatformAccessService::class)->isPlatformOperator($user)) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function latestRecoveryEvent(int $companyId): ?TenantAuditEvent
    {
        return TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('action', [
                'tenant.execution.billing.process_stuck_queued',
                'tenant.execution.payout.process_stuck_queued',
                'tenant.execution.billing.auto_recovered_queued',
                'tenant.execution.payout.auto_recovered_queued',
                'tenant.execution.webhook.auto_recovered_queued',
                'tenant.execution.auto_recovery.run_summary',
                'tenant.execution.webhook.manual_reconciled_billing',
                'tenant.execution.webhook.manual_reconciled_payout',
            ])
            ->latest('event_at')
            ->latest('id')
            ->first(['id', 'action', 'event_at']);
    }

    private function formatIncidentId(int $eventId): string
    {
        return 'EXE-'.str_pad((string) max(0, $eventId), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function summaryLabel(string $action, array $metadata): string
    {
        $pipeline = ucfirst((string) ($metadata['pipeline'] ?? 'execution'));

        return match ($action) {
            'tenant.execution.alert.summary_emitted' => $pipeline.' pipeline requires attention.',
            'tenant.execution.auto_recovery.run_summary' => $pipeline.' recovery run completed.',
            default => 'Execution summary recorded.',
        };
    }

    /**
     * @return array{
     *     status_label:string,
     *     status_tone:string,
     *     last_recovery_outcome_at:string,
     *     affected_billings:int,
     *     affected_payouts:int,
     *     next_action:string,
     *     current_incident_id:?string
     * }
     */
    private function emptySummary(): array
    {
        return [
            'status_label' => 'Healthy',
            'status_tone' => 'healthy',
            'last_recovery_outcome_at' => 'No recovery outcome yet.',
            'affected_billings' => 0,
            'affected_payouts' => 0,
            'next_action' => 'Retry later.',
            'current_incident_id' => null,
        ];
    }
}


