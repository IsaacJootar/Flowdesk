<?php

namespace App\Actions\Requests;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RequestApprovalSlaService;
use App\Services\RequestCommunicationLogger;
use App\Services\RequestApprovalRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubmitSpendRequest
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RequestApprovalRouter $requestApprovalRouter,
        private readonly RequestCommunicationLogger $requestCommunicationLogger,
        private readonly RequestApprovalSlaService $requestApprovalSlaService
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, SpendRequest $request, ?array $selectedChannels = null): SpendRequest
    {
        Gate::forUser($user)->authorize('submit', $request);

        if (! in_array((string) $request->status, ['draft', 'returned'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or returned requests can be submitted.',
            ]);
        }

        $workflow = $this->requestApprovalRouter->resolveActiveWorkflow($request);
        $steps = $workflow?->steps()->where('is_active', true)->orderBy('step_order')->get() ?? collect();

        if (! $workflow || $steps->isEmpty()) {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval workflow is configured for requests.',
            ]);
        }

        $firstStep = $steps->first();
        $organizationChannels = $this->organizationSelectableChannels((int) $request->company_id);
        $requestChannelOverride = $selectedChannels === null
            ? []
            : array_values(array_unique(array_intersect(
                array_map('strval', $selectedChannels),
                CompanyCommunicationSetting::CHANNELS
            )));
        $requestChannelMode = $selectedChannels === null ? 'workflow_default' : 'custom';

        if ($requestChannelMode === 'custom' && $requestChannelOverride === []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'Select at least one notification channel before submitting.',
            ]);
        }

        $invalid = array_values(array_diff($requestChannelOverride, $organizationChannels));
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'Selected channel is not enabled/configured: '.implode(', ', $invalid).'.',
            ]);
        }

        DB::transaction(function () use ($user, $request, $workflow, $steps, $firstStep, $organizationChannels, $requestChannelMode, $requestChannelOverride): void {
            $metadata = array_merge((array) ($request->metadata ?? []), [
                'channel_mode' => $requestChannelMode,
                'notification_channels' => $requestChannelOverride,
            ]);
            $request->forceFill([
                'workflow_id' => $workflow->id,
                'status' => 'in_review',
                'current_approval_step' => (int) $firstStep->step_order,
                'submitted_at' => now(),
                'decided_at' => null,
                'decision_note' => null,
                'metadata' => $metadata,
                'updated_by' => $user->id,
            ])->save();

            foreach ($steps as $step) {
                $isFirstStep = (int) $step->step_order === (int) $firstStep->step_order;
                $stepMetadata = [
                    'actor_type' => $step->actor_type,
                    'actor_value' => $step->actor_value,
                    'notification_channels' => $this->resolveStepChannels(
                        $organizationChannels,
                        (array) ($step->notification_channels ?? []),
                        $requestChannelMode,
                        $requestChannelOverride
                    ),
                    'request_channel_mode' => $requestChannelMode,
                    'sla' => $this->requestApprovalSlaService->defaultMetadata(),
                ];

                RequestApproval::query()->updateOrCreate(
                    [
                        'company_id' => (int) $request->company_id,
                        'request_id' => $request->id,
                        'step_order' => (int) $step->step_order,
                    ],
                    [
                        'workflow_step_id' => $step->id,
                        'step_key' => $step->step_key,
                        'status' => $isFirstStep ? 'pending' : 'queued',
                        'action' => null,
                        'acted_by' => null,
                        'acted_at' => null,
                        'due_at' => $isFirstStep
                            ? $this->requestApprovalSlaService->dueAtFromNow($stepMetadata)
                            : null,
                        'reminder_sent_at' => null,
                        'escalated_at' => null,
                        'reminder_count' => 0,
                        'comment' => null,
                        'from_status' => null,
                        'to_status' => null,
                        'metadata' => $stepMetadata,
                    ]
                );
            }
        });

        $this->activityLogger->log(
            action: 'request.submitted',
            entityType: SpendRequest::class,
            entityId: $request->id,
            metadata: [
                'request_code' => $request->request_code,
                'workflow_id' => $workflow->id,
                'workflow_name' => $workflow->name,
                'initial_step_order' => (int) $firstStep->step_order,
                'initial_step_key' => $firstStep->step_key,
            ],
            companyId: (int) $request->company_id,
            userId: $user->id,
        );

        $fresh = $request->fresh(['workflow', 'items', 'approvals.workflowStep']) ?? $request;
        $firstApproval = $fresh->approvals
            ->firstWhere('step_order', (int) $firstStep->step_order);
        $firstStepChannels = (array) (($firstApproval?->metadata ?? [])['notification_channels'] ?? []);
        $firstRecipients = $this->requestApprovalRouter
            ->resolveEligibleApprovers($firstStep, $fresh)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $this->requestCommunicationLogger->log(
            request: $fresh,
            event: 'request.submitted',
            channels: $firstStepChannels,
            recipientUserIds: $firstRecipients,
            requestApprovalId: $firstApproval?->id ? (int) $firstApproval->id : null,
            metadata: [
                'request_code' => (string) $fresh->request_code,
                'status' => (string) $fresh->status,
                'audience' => 'current_step_approvers',
            ]
        );

        return $fresh;
    }

    /**
     * @return array<int, string>
     */
    private function organizationSelectableChannels(int $companyId): array
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyCommunicationSetting::defaultAttributes()
            );

        return $settings->selectableChannels();
    }

    /**
     * @param  array<int, string>  $organizationChannels
     * @param  array<int, string>  $stepChannels
     * @param  array<int, string>  $requestChannels
     * @return array<int, string>
     */
    private function resolveStepChannels(
        array $organizationChannels,
        array $stepChannels,
        string $requestChannelMode,
        array $requestChannels
    ): array {
        $stepChannels = array_values(array_unique(array_map('strval', $stepChannels)));
        if ($stepChannels === []) {
            $stepChannels = $organizationChannels;
        }

        $stepEffective = array_values(array_intersect($stepChannels, $organizationChannels));
        if ($requestChannelMode !== 'custom') {
            return $stepEffective;
        }

        return array_values(array_intersect($stepEffective, $requestChannels));
    }
}
