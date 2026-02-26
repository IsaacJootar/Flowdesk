<?php

namespace App\Services;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RequestApprovalSlaProcessor
{
    public function __construct(
        private readonly RequestApprovalSlaService $slaService,
        private readonly ApprovalTimingPolicyResolver $approvalTimingPolicyResolver,
        private readonly RequestApprovalRouter $requestApprovalRouter,
        private readonly RequestCommunicationLogger $requestCommunicationLogger
    ) {
    }

    /**
     * @return array{pending_scanned:int, initialized_due_at:int, reminders_sent:int, escalations_sent:int}
     */
    public function process(?int $companyId = null, bool $dryRun = false): array
    {
        $stats = [
            'pending_scanned' => 0,
            'initialized_due_at' => 0,
            'reminders_sent' => 0,
            'escalations_sent' => 0,
        ];

        $now = now()->toImmutable();

        $query = RequestApproval::query()
            ->where('status', 'pending')
            ->whereNull('acted_at')
            ->with([
                'workflowStep:id,company_id,workflow_id,step_order,step_key,actor_type,actor_value,notification_channels',
                'request:id,company_id,request_code,requested_by,department_id,workflow_id,current_approval_step,status,title',
                'request.department:id,company_id,manager_user_id',
            ]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $query->chunkById(100, function (Collection $approvals) use (&$stats, $dryRun, $now): void {
            /** @var RequestApproval $approval */
            foreach ($approvals as $approval) {
                $stats['pending_scanned']++;
                $this->processSingleApproval($approval, $now, $dryRun, $stats);
            }
        });

        return $stats;
    }

    /**
     * @param  array{pending_scanned:int, initialized_due_at:int, reminders_sent:int, escalations_sent:int}  $stats
     */
    private function processSingleApproval(
        RequestApproval $approval,
        CarbonImmutable $now,
        bool $dryRun,
        array &$stats
    ): void {
        $request = $approval->request;
        // Skip stale rows; SLA actions only apply while request is actively in review.
        if (! $request instanceof SpendRequest || (string) $request->status !== 'in_review') {
            return;
        }

        $metadata = is_array($approval->metadata) ? $approval->metadata : [];
        $dirty = false;

        if (! is_array(data_get($metadata, 'sla'))) {
            $metadata['sla'] = $this->approvalTimingPolicyResolver->resolve(
                companyId: (int) $request->company_id,
                departmentId: $request->department_id ? (int) $request->department_id : null
            );
            $approval->metadata = $metadata;
            $dirty = true;
        }

        if (! $approval->due_at) {
            $approval->due_at = $this->slaService->dueAtFromNow($metadata, $now);
            $stats['initialized_due_at']++;
            $dirty = true;
        }

        if (! $approval->due_at) {
            return;
        }

        $dueAt = CarbonImmutable::instance($approval->due_at);
        $reminderAt = $this->slaService->reminderAt($dueAt, $metadata);
        $escalationAt = $this->slaService->escalationAt($dueAt, $metadata);
        $channels = $this->resolveChannels($approval);

        if (! $approval->reminder_sent_at && $now->greaterThanOrEqualTo($reminderAt) && $now->lessThan($escalationAt)) {
            // One reminder window before escalation kicks in.
            $recipientIds = $this->currentStepApproverIds($approval, $request);

            if (! $dryRun && $recipientIds !== []) {
                $this->requestCommunicationLogger->log(
                    request: $request,
                    event: 'request.approval.reminder',
                    channels: $channels,
                    recipientUserIds: $recipientIds,
                    requestApprovalId: (int) $approval->id,
                    metadata: [
                        'request_code' => (string) $request->request_code,
                        'status' => (string) $request->status,
                        'step_order' => (int) $approval->step_order,
                        'due_at' => $dueAt->toIso8601String(),
                    ]
                );
            }

            $approval->reminder_sent_at = $now;
            $approval->reminder_count = (int) ($approval->reminder_count ?? 0) + 1;
            $stats['reminders_sent']++;
            $dirty = true;
        }

        if (! $approval->escalated_at && $now->greaterThanOrEqualTo($escalationAt)) {
            // Escalation notifies supervisory roles and department leadership.
            $currentApproverIds = $this->currentStepApproverIds($approval, $request);
            $escalationRecipientIds = $this->escalationRecipientIds($request, $currentApproverIds);

            if (! $dryRun && $escalationRecipientIds !== []) {
                $this->requestCommunicationLogger->log(
                    request: $request,
                    event: 'request.approval.escalated',
                    channels: $channels,
                    recipientUserIds: $escalationRecipientIds,
                    requestApprovalId: (int) $approval->id,
                    metadata: [
                        'request_code' => (string) $request->request_code,
                        'status' => (string) $request->status,
                        'step_order' => (int) $approval->step_order,
                        'due_at' => $dueAt->toIso8601String(),
                        'escalated_after_hours' => (int) $dueAt->diffInHours($now),
                    ]
                );
            }

            $approval->escalated_at = $now;
            $metadata['sla'] = array_merge(
                (array) ($metadata['sla'] ?? []),
                [
                    'escalated_at' => $now->toIso8601String(),
                    'escalated_to_user_ids' => $escalationRecipientIds,
                ]
            );
            $approval->metadata = $metadata;
            $stats['escalations_sent']++;
            $dirty = true;
        }

        if ($dirty && ! $dryRun) {
            $approval->save();
        }
    }

    /**
     * @return array<int, int>
     */
    private function currentStepApproverIds(RequestApproval $approval, SpendRequest $request): array
    {
        $step = $approval->workflowStep ?: $this->requestApprovalRouter->resolveCurrentStep($request);
        if (! $step) {
            return [];
        }

        return $this->requestApprovalRouter
            ->resolveEligibleApprovers($step, $request)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $excludeUserIds
     * @return array<int, int>
     */
    private function escalationRecipientIds(SpendRequest $request, array $excludeUserIds): array
    {
        $query = User::query()
            ->where('company_id', (int) $request->company_id)
            ->where('is_active', true)
            ->where(function (Builder $builder): void {
                $builder
                    ->where('role', UserRole::Owner->value)
                    ->orWhere('role', UserRole::Finance->value);
            });

        $ids = $query->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $departmentManagerId = (int) ($request->department?->manager_user_id ?? 0);
        if ($departmentManagerId > 0) {
            $ids[] = $departmentManagerId;
        }

        return collect($ids)
            ->filter(fn (int $id): bool => $id > 0 && ! in_array($id, $excludeUserIds, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveChannels(RequestApproval $approval): array
    {
        $metadata = is_array($approval->metadata) ? $approval->metadata : [];
        $channels = array_values(array_unique(array_map(
            'strval',
            (array) ($metadata['notification_channels'] ?? [])
        )));

        if ($channels === []) {
            $channels = array_values(array_unique(array_map(
                'strval',
                (array) ($approval->workflowStep?->notification_channels ?? [])
            )));
        }

        return $channels === [] ? ['in_app'] : $channels;
    }
}
