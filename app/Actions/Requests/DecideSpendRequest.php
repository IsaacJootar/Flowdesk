<?php

namespace App\Actions\Requests;

use App\Domains\Approvals\Models\ApprovalWorkflowStep;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DecideSpendRequest
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
    public function __invoke(User $user, SpendRequest $request, array $input, ?array $selectedChannels = null): SpendRequest
    {
        Gate::forUser($user)->authorize('approve', $request);

        $validated = Validator::make($input, [
            'action' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        if (in_array((string) $validated['action'], ['reject', 'return'], true) && trim((string) ($validated['comment'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'comment' => 'Comment is required for reject or return actions.',
            ]);
        }

        if ((string) $request->status !== 'in_review') {
            throw ValidationException::withMessages([
                'status' => 'Only requests under review can be decided.',
            ]);
        }

        $currentStep = $this->requestApprovalRouter->resolveCurrentStep($request);

        if (! $currentStep) {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval step was found for this request.',
            ]);
        }

        $eligibleApprovers = $this->requestApprovalRouter->resolveEligibleApprovers($currentStep, $request);
        $isEligible = $eligibleApprovers->contains(fn (User $approver): bool => (int) $approver->id === (int) $user->id);

        if (! $isEligible) {
            throw ValidationException::withMessages([
                'approver' => 'You are not an eligible approver for this step.',
            ]);
        }

        $action = (string) $validated['action'];
        $comment = $this->nullableString($validated['comment'] ?? null);
        $notificationChannels = $this->resolveDecisionChannels($request, $currentStep, $selectedChannels);

        DB::transaction(function () use ($request, $user, $currentStep, $action, $comment): void {
            $fromStatus = (string) $request->status;
            $nextStep = null;
            $toStatus = 'in_review';

            if ($action === 'approve') {
                $nextStep = ApprovalWorkflowStep::query()
                    ->where('company_id', (int) $request->company_id)
                    ->where('workflow_id', (int) $currentStep->workflow_id)
                    ->where('is_active', true)
                    ->where('step_order', '>', (int) $currentStep->step_order)
                    ->orderBy('step_order')
                    ->first();

                if ($nextStep) {
                    $toStatus = 'in_review';
                    $request->forceFill([
                        'status' => $toStatus,
                        'current_approval_step' => (int) $nextStep->step_order,
                        'decision_note' => null,
                        'decided_at' => null,
                        'updated_by' => $user->id,
                    ])->save();

                    RequestApproval::query()->updateOrCreate(
                        [
                            'company_id' => (int) $request->company_id,
                            'request_id' => $request->id,
                            'step_order' => (int) $nextStep->step_order,
                        ],
                        [
                            'workflow_step_id' => $nextStep->id,
                            'step_key' => $nextStep->step_key,
                            'status' => 'pending',
                            'due_at' => $this->requestApprovalSlaService->dueAtFromNow([
                                'sla' => $this->requestApprovalSlaService->defaultMetadata(),
                            ]),
                            'reminder_sent_at' => null,
                            'escalated_at' => null,
                            'reminder_count' => 0,
                            'metadata' => [
                                'actor_type' => $nextStep->actor_type,
                                'actor_value' => $nextStep->actor_value,
                                'sla' => $this->requestApprovalSlaService->defaultMetadata(),
                            ],
                        ]
                    );
                } else {
                    $toStatus = 'approved';
                    $request->forceFill([
                        'status' => $toStatus,
                        'current_approval_step' => null,
                        'approved_amount' => (int) $request->amount,
                        'decision_note' => $comment,
                        'decided_at' => now(),
                        'updated_by' => $user->id,
                    ])->save();
                }
            } elseif ($action === 'reject') {
                $toStatus = 'rejected';
                $request->forceFill([
                    'status' => $toStatus,
                    'current_approval_step' => null,
                    'decision_note' => $comment,
                    'decided_at' => now(),
                    'updated_by' => $user->id,
                ])->save();
            } else {
                $toStatus = 'returned';
                $request->forceFill([
                    'status' => $toStatus,
                    'current_approval_step' => null,
                    'decision_note' => $comment,
                    'decided_at' => null,
                    'updated_by' => $user->id,
                ])->save();
            }

            RequestApproval::query()->updateOrCreate(
                [
                    'company_id' => (int) $request->company_id,
                    'request_id' => $request->id,
                    'step_order' => (int) $currentStep->step_order,
                ],
                [
                    'workflow_step_id' => $currentStep->id,
                    'step_key' => $currentStep->step_key,
                    'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'returned'),
                    'action' => $action,
                    'acted_by' => $user->id,
                    'acted_at' => now(),
                    'comment' => $comment,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'metadata' => [
                        'actor_type' => $currentStep->actor_type,
                        'actor_value' => $currentStep->actor_value,
                    ],
                ]
            );
        });

        $fresh = $request->fresh(['workflow', 'items', 'approvals.workflowStep']) ?? $request;

        $event = match ($action) {
            'approve' => ((string) $fresh->status === 'approved') ? 'request.approved' : 'request.step.approved',
            'reject' => 'request.rejected',
            default => 'request.returned',
        };

        $this->activityLogger->log(
            action: $event,
            entityType: SpendRequest::class,
            entityId: $fresh->id,
            metadata: [
                'request_code' => $fresh->request_code,
                'action' => $action,
                'status' => $fresh->status,
                'current_approval_step' => $fresh->current_approval_step,
                'comment' => $comment,
            ],
            companyId: (int) $fresh->company_id,
            userId: $user->id,
        );

        $currentApproval = $fresh->approvals->firstWhere('step_order', (int) $currentStep->step_order);
        if ($action === 'approve' && (string) $fresh->status === 'in_review') {
            $nextStep = $this->requestApprovalRouter->resolveCurrentStep($fresh);
            $nextRecipients = $nextStep
                ? $this->requestApprovalRouter->resolveEligibleApprovers($nextStep, $fresh)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all()
                : [];

            $this->requestCommunicationLogger->log(
                request: $fresh,
                event: 'request.step.approved',
                channels: $notificationChannels,
                recipientUserIds: $nextRecipients,
                requestApprovalId: $currentApproval?->id ? (int) $currentApproval->id : null,
                metadata: [
                    'request_code' => (string) $fresh->request_code,
                    'status' => (string) $fresh->status,
                    'action' => $action,
                    'audience' => 'next_step_approvers',
                ]
            );
        } else {
            $event = match ($action) {
                'approve' => 'request.approved',
                'reject' => 'request.rejected',
                default => 'request.returned',
            };

            $this->requestCommunicationLogger->log(
                request: $fresh,
                event: $event,
                channels: $notificationChannels,
                recipientUserIds: [(int) $fresh->requested_by],
                requestApprovalId: $currentApproval?->id ? (int) $currentApproval->id : null,
                metadata: [
                    'request_code' => (string) $fresh->request_code,
                    'status' => (string) $fresh->status,
                    'action' => $action,
                    'audience' => 'requester',
                ]
            );
        }

        return $fresh;
    }

    /**
     * @param  array<int, string>|null  $selectedChannels
     * @return array<int, string>
     * @throws ValidationException
     */
    private function resolveDecisionChannels(
        SpendRequest $request,
        ApprovalWorkflowStep $currentStep,
        ?array $selectedChannels
    ): array {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $request->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );
        $organizationChannels = $settings->selectableChannels();

        $currentApproval = RequestApproval::query()
            ->where('company_id', (int) $request->company_id)
            ->where('request_id', (int) $request->id)
            ->where('step_order', (int) $currentStep->step_order)
            ->first();

        $stepChannels = array_values(array_unique(array_map(
            'strval',
            (array) (($currentApproval?->metadata ?? [])['notification_channels'] ?? $currentStep->notification_channels ?? [])
        )));

        if ($stepChannels === []) {
            $stepChannels = $organizationChannels;
        } else {
            $stepChannels = array_values(array_intersect($stepChannels, $organizationChannels));
        }

        if ($selectedChannels === null) {
            return $stepChannels;
        }

        $selectedChannels = array_values(array_unique(array_intersect(
            array_map('strval', $selectedChannels),
            CompanyCommunicationSetting::CHANNELS
        )));

        if ($selectedChannels === []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'Select at least one notification channel for this approval action.',
            ]);
        }

        $invalid = array_values(array_diff($selectedChannels, $stepChannels));
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'notification_channels' => 'Selected channel is not enabled for this approval step: '.implode(', ', $invalid).'.',
            ]);
        }

        return $selectedChannels;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
