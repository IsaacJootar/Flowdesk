<?php

namespace App\Livewire\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

    public string $focusPipeline = '';

    public ?int $focusBillingAttemptId = null;

    public ?int $focusPayoutAttemptId = null;

    public ?int $focusWebhookEventId = null;

    public ?string $focusIncidentId = null;

    public bool $focusRequested = false;

    /**
     * @var array{
     *     pipeline:string,
     *     record_label:string,
     *     status:string,
     *     provider:string,
     *     reference:string,
     *     amount:string,
     *     event_time:string,
     *     incident_id:?string,
     *     next_action:string
     * }|null
     */
    public ?array $focusContext = null;

    public ?string $focusContextMessage = null;

    public function mount(): void
    {
        abort_unless($this->canAccessPage(), 403);

        $this->summary = $this->emptySummary();
        $this->recentSummaries = [];
        $this->hydrateFocusFromQuery();
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
        $this->summary = $this->buildSummary();
        $this->recentSummaries = $this->buildRecentSummaries();
        [$this->focusContext, $this->focusContextMessage] = $this->buildFocusContext();
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
        $nextAction = 'No action needed right now.';

        if ($hasRecentAlert) {
            $statusLabel = 'Action needed';
            $statusTone = 'action_needed';
            $currentIncidentId = $this->formatIncidentId((int) $latestAlert->id);
            $nextAction = 'Contact support with incident ID '.$currentIncidentId.'.';
        } elseif (($affectedBillings + $affectedPayouts) > 0) {
            $statusLabel = 'Delayed';
            $statusTone = 'delayed';
            $nextAction = 'Open Execution & Payouts to run recovery or retry affected records.';
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
                'tenant.execution.alert.notification.sent',
                'tenant.execution.alert.notification.failed',
                'tenant.execution.payout.manual_queue_run',
                'tenant.execution.billing.process_stuck_queued',
                'tenant.execution.payout.process_stuck_queued',
                'tenant.execution.billing.auto_recovered_queued',
                'tenant.execution.payout.auto_recovered_queued',
                'tenant.execution.webhook.auto_recovered_queued',
                'tenant.execution.webhook.manual_reconciled_billing',
                'tenant.execution.webhook.manual_reconciled_payout',
                'tenant.execution.webhook.manual_failed',
                'tenant.execution.webhook.manual_ignored',
                'tenant.execution.alert.notification.skipped',
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

    /**
     * @return array{0:array{pipeline:string,record_label:string,status:string,provider:string,reference:string,amount:string,event_time:string,incident_id:?string,next_action:string}|null,1:?string}
     */
    private function buildFocusContext(): array
    {
        if (! $this->focusRequested) {
            return [null, null];
        }

        $companyId = (int) (Auth::user()?->company_id ?? 0);
        if ($companyId <= 0) {
            return [null, 'Unable to resolve linked execution context for this user scope.'];
        }

        return match ($this->focusPipeline) {
            'billing' => $this->billingFocusContext($companyId),
            'payout' => $this->payoutFocusContext($companyId),
            'webhook' => $this->webhookFocusContext($companyId),
            default => [null, 'Linked execution context type is not supported.'],
        };
    }

    private function canAccessPage(): bool
    {
        return Gate::allows('viewAny', RequestPayoutExecutionAttempt::class);
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
        $channel = strtolower((string) ($metadata['channel'] ?? 'in_app'));
        $channelLabel = match ($channel) {
            'email' => 'Email',
            'in_app' => 'In-app',
            default => ucfirst(str_replace('_', ' ', $channel)),
        };

        return match ($action) {
            'tenant.execution.alert.summary_emitted' => $pipeline.' pipeline requires attention.',
            'tenant.execution.auto_recovery.run_summary' => $pipeline.' recovery run completed.',
            'tenant.execution.alert.notification.sent' => 'Execution alert delivered via '.$channelLabel.'.',
            'tenant.execution.alert.notification.failed' => 'Execution alert delivery failed via '.$channelLabel.'.',
            'tenant.execution.payout.manual_queue_run' => 'Manual payout queue recovery run completed.',
            'tenant.execution.billing.process_stuck_queued' => 'Billing queued records were reviewed for recovery.',
            'tenant.execution.payout.process_stuck_queued' => 'Payout queued records were reviewed for recovery.',
            'tenant.execution.billing.auto_recovered_queued' => 'Billing queued records were auto-recovered.',
            'tenant.execution.payout.auto_recovered_queued' => 'Payout queued records were auto-recovered.',
            'tenant.execution.webhook.auto_recovered_queued' => 'Webhook queued records were auto-recovered.',
            'tenant.execution.webhook.manual_reconciled_billing' => 'Webhook manually reconciled to billing.',
            'tenant.execution.webhook.manual_reconciled_payout' => 'Webhook manually reconciled to payout.',
            'tenant.execution.webhook.manual_failed' => 'Webhook marked as manually failed.',
            'tenant.execution.webhook.manual_ignored' => 'Webhook marked as manually ignored.',
            'tenant.execution.alert.notification.skipped' => 'Execution alert delivery was skipped.',
            default => 'Execution summary recorded.',
        };
    }

    /**
     * @return array{0:array{pipeline:string,record_label:string,status:string,provider:string,reference:string,amount:string,event_time:string,incident_id:?string,next_action:string}|null,1:?string}
     */
    private function billingFocusContext(int $companyId): array
    {
        if (! $this->focusBillingAttemptId) {
            return [null, 'Billing attempt reference is missing in this link.'];
        }

        $attempt = TenantSubscriptionBillingAttempt::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $this->focusBillingAttemptId)
            ->first([
                'id',
                'attempt_status',
                'provider_key',
                'provider_reference',
                'external_invoice_id',
                'amount',
                'currency_code',
                'updated_at',
            ]);

        if (! $attempt) {
            return [null, 'Linked billing attempt is not available in your tenant scope anymore.'];
        }

        $status = (string) $attempt->attempt_status;

        return [[
            'pipeline' => 'Billing',
            'record_label' => 'Billing attempt #'.(int) $attempt->id,
            'status' => $status,
            'provider' => (string) ($attempt->provider_key ?? '-'),
            'reference' => (string) ($attempt->provider_reference ?: $attempt->external_invoice_id ?: '-'),
            'amount' => number_format((float) $attempt->amount, 2).' '.strtoupper((string) ($attempt->currency_code ?? 'NGN')),
            'event_time' => $attempt->updated_at?->format('M d, Y H:i') ?? '-',
            'incident_id' => $this->focusIncidentId,
            'next_action' => $status === 'failed'
                ? 'Confirm provider and billing configuration, then retry from operations.'
                : 'Monitor status transition and reconcile if needed.',
        ], null];
    }

    /**
     * @return array{0:array{pipeline:string,record_label:string,status:string,provider:string,reference:string,amount:string,event_time:string,incident_id:?string,next_action:string}|null,1:?string}
     */
    private function payoutFocusContext(int $companyId): array
    {
        if (! $this->focusPayoutAttemptId) {
            return [null, 'Payout attempt reference is missing in this link.'];
        }

        $attempt = RequestPayoutExecutionAttempt::query()
            ->with('request:id,request_code')
            ->where('company_id', $companyId)
            ->whereKey((int) $this->focusPayoutAttemptId)
            ->first([
                'id',
                'request_id',
                'execution_status',
                'provider_key',
                'provider_reference',
                'external_transfer_id',
                'amount',
                'currency_code',
                'updated_at',
            ]);

        if (! $attempt) {
            return [null, 'Linked payout attempt is not available in your tenant scope anymore.'];
        }

        $status = (string) $attempt->execution_status;
        $requestCode = (string) ($attempt->request?->request_code ?? 'N/A');

        return [[
            'pipeline' => 'Payout',
            'record_label' => 'Payout attempt #'.(int) $attempt->id.' ('.$requestCode.')',
            'status' => $status,
            'provider' => (string) ($attempt->provider_key ?? '-'),
            'reference' => (string) ($attempt->provider_reference ?: $attempt->external_transfer_id ?: '-'),
            'amount' => number_format((float) $attempt->amount, 2).' '.strtoupper((string) ($attempt->currency_code ?? 'NGN')),
            'event_time' => $attempt->updated_at?->format('M d, Y H:i') ?? '-',
            'incident_id' => $this->focusIncidentId,
            'next_action' => $status === 'failed'
                ? 'Confirm payout request linkage and provider readiness, then retry from operations.'
                : 'Monitor status transition and reconcile if needed.',
        ], null];
    }

    /**
     * @return array{0:array{pipeline:string,record_label:string,status:string,provider:string,reference:string,amount:string,event_time:string,incident_id:?string,next_action:string}|null,1:?string}
     */
    private function webhookFocusContext(int $companyId): array
    {
        if (! $this->focusWebhookEventId) {
            return [null, 'Webhook event reference is missing in this link.'];
        }

        $event = ExecutionWebhookEvent::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $this->focusWebhookEventId)
            ->first([
                'id',
                'provider_key',
                'external_event_id',
                'event_type',
                'verification_status',
                'processing_status',
                'received_at',
            ]);

        if (! $event) {
            return [null, 'Linked webhook event is not available in your tenant scope anymore.'];
        }

        $status = (string) $event->processing_status;

        return [[
            'pipeline' => 'Webhook',
            'record_label' => 'Webhook event #'.(int) $event->id,
            'status' => $status,
            'provider' => (string) ($event->provider_key ?? '-'),
            'reference' => (string) ($event->external_event_id ?: $event->event_type ?: '-'),
            'amount' => '-',
            'event_time' => $event->received_at?->format('M d, Y H:i') ?? '-',
            'incident_id' => $this->focusIncidentId,
            'next_action' => $status === 'failed'
                ? 'Review webhook verification/mapping state and run manual reconcile when ready.'
                : 'Monitor webhook lifecycle and reconcile if needed.',
        ], null];
    }

    private function hydrateFocusFromQuery(): void
    {
        $query = request()->query();

        $pipeline = strtolower(trim((string) ($query['focus_pipeline'] ?? '')));
        if (! in_array($pipeline, ['billing', 'payout', 'webhook'], true)) {
            $this->focusRequested = false;

            return;
        }

        $this->focusPipeline = $pipeline;
        $this->focusBillingAttemptId = $this->positiveInt($query['billing_attempt_id'] ?? null);
        $this->focusPayoutAttemptId = $this->positiveInt($query['payout_attempt_id'] ?? null);
        $this->focusWebhookEventId = $this->positiveInt($query['webhook_event_id'] ?? null);

        $incidentId = strtoupper(trim((string) ($query['incident_id'] ?? '')));
        $this->focusIncidentId = $incidentId !== '' ? $incidentId : null;
        $this->focusRequested = true;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
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
            'next_action' => 'No action needed right now.',
            'current_incident_id' => null,
        ];
    }
}


