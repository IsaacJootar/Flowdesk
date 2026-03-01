<?php

namespace App\Actions\Requests;

use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\ApprovalTimingPolicyResolver;
use App\Services\ActivityLogger;
use App\Services\Execution\RequestPayoutExecutionOrchestrator;
use App\Services\RequestApprovalSlaService;
use App\Services\RequestCommunicationLogger;
use App\Services\RequestApprovalRouter;
use App\Services\TenantExecutionModeService;
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
        private readonly RequestApprovalSlaService $requestApprovalSlaService,
        private readonly ApprovalTimingPolicyResolver $approvalTimingPolicyResolver,
        private readonly RequestPayoutExecutionOrchestrator $requestPayoutExecutionOrchestrator
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

        $currentScope = $this->requestApprovalRouter->currentScope($request);
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

        $transitionedToPaymentAuthorization = false;
        $shouldQueueExecution = false;

        DB::transaction(function () use ($request, $user, $currentStep, $action, $comment, $currentScope, &$transitionedToPaymentAuthorization, &$shouldQueueExecution): void {
            // This block is the request decision state machine (approve/reject/return).
            $fromStatus = (string) $request->status;
            $nextStep = null;
            $toStatus = 'in_review';

            if ($action === 'approve') {
                $nextStep = $this->requestApprovalRouter->resolveNextStep($request, (int) $currentStep->step_order);

                if ($nextStep) {
                    $nextStepSla = $this->approvalTimingPolicyResolver->resolve(
                        companyId: (int) $request->company_id,
                        departmentId: $request->department_id ? (int) $request->department_id : null,
                        stepLevelSla: (array) data_get((array) ($nextStep->metadata ?? []), 'sla', [])
                    );

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
                            'scope' => $currentScope,
                            'step_order' => (int) $nextStep->step_order,
                        ],
                        [
                            'workflow_step_id' => $nextStep->id,
                            'step_key' => $nextStep->step_key,
                            'status' => 'pending',
                            'due_at' => $this->requestApprovalSlaService->dueAtFromNow([
                                'sla' => $nextStepSla,
                            ]),
                            'reminder_sent_at' => null,
                            'escalated_at' => null,
                            'reminder_count' => 0,
                            'metadata' => [
                                'actor_type' => $nextStep->actor_type,
                                'actor_value' => $nextStep->actor_value,
                                'notification_channels' => (array) ($nextStep->notification_channels ?? []),
                                'sla' => $nextStepSla,
                            ],
                        ]
                    );
                } else {
                    if ($currentScope === RequestApprovalRouter::SCOPE_REQUEST && $this->usesExecutionEnabledMode($request)) {
                        $transitionedToPaymentAuthorization = $this->startPaymentAuthorizationPhase($request, $user);

                        if ($transitionedToPaymentAuthorization) {
                            $toStatus = 'in_review';
                        } else {
                            // Missing/empty payment-authorization flow falls back to execution queue path.
                            $toStatus = 'approved_for_execution';
                            $request->forceFill([
                                'status' => $toStatus,
                                'current_approval_step' => null,
                                'approved_amount' => (int) ($request->approved_amount ?: $request->amount),
                                'decision_note' => $comment,
                                'decided_at' => now(),
                                'updated_by' => $user->id,
                            ])->save();
                            $shouldQueueExecution = true;
                        }
                    } elseif ($currentScope === RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION && $this->usesExecutionEnabledMode($request)) {
                        $toStatus = 'approved_for_execution';
                        $request->forceFill([
                            'status' => $toStatus,
                            'current_approval_step' => null,
                            'approved_amount' => (int) ($request->approved_amount ?: $request->amount),
                            'decision_note' => $comment,
                            'decided_at' => now(),
                            'updated_by' => $user->id,
                        ])->save();
                        $shouldQueueExecution = true;
                    } else {
                        $toStatus = 'approved';
                        $request->forceFill([
                            'status' => $toStatus,
                            'current_approval_step' => null,
                            'approved_amount' => (int) ($request->approved_amount ?: $request->amount),
                            'decision_note' => $comment,
                            'decided_at' => now(),
                            'updated_by' => $user->id,
                        ])->save();
                    }
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

            // Persist the current step audit row for timeline/history even when final status changes.
            RequestApproval::query()->updateOrCreate(
                [
                    'company_id' => (int) $request->company_id,
                    'request_id' => $request->id,
                    'scope' => $currentScope,
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

        $fresh = $request->fresh(['workflow', 'items', 'approvals.workflowStep', 'company.subscription']) ?? $request;

        if ($action === 'approve' && $shouldQueueExecution) {
            $this->requestPayoutExecutionOrchestrator->queueForApprovedRequest($fresh, $user->id);
            $fresh = $fresh->fresh(['workflow', 'items', 'approvals.workflowStep', 'company.subscription']) ?? $fresh;
        }

        $event = match ($action) {
            'approve' => $transitionedToPaymentAuthorization
                ? 'request.payment_authorization.started'
                : match ((string) $fresh->status) {
                    'approved' => 'request.approved',
                    'approved_for_execution' => 'request.approved_for_execution',
                    'execution_queued' => 'request.execution.queued',
                    'execution_processing' => 'request.execution.processing',
                    default => 'request.step.approved',
                },
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
                'scope' => $this->requestApprovalRouter->currentScope($fresh),
                'comment' => $comment,
            ],
            companyId: (int) $fresh->company_id,
            userId: $user->id,
        );

        $freshScope = $this->requestApprovalRouter->currentScope($fresh);
        $currentApproval = $fresh->approvals->first(fn (RequestApproval $approval): bool =>
            (string) ($approval->scope ?: RequestApprovalRouter::SCOPE_REQUEST) === $currentScope
            && (int) $approval->step_order === (int) $currentStep->step_order
        );

        if ($action === 'approve' && (string) $fresh->status === 'in_review') {
            // Mid-chain approval notifies next step approvers (including scope transitions).
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
                event: $transitionedToPaymentAuthorization ? 'request.payment_authorization.started' : 'request.step.approved',
                channels: $notificationChannels,
                recipientUserIds: $nextRecipients,
                requestApprovalId: $currentApproval?->id ? (int) $currentApproval->id : null,
                metadata: [
                    'request_code' => (string) $fresh->request_code,
                    'status' => (string) $fresh->status,
                    'scope' => $freshScope,
                    'action' => $action,
                    'audience' => 'next_step_approvers',
                ]
            );
        } else {
            // Final decisions notify the original requester.
            $requesterEvent = match ($action) {
                'approve' => match ((string) $fresh->status) {
                    'approved' => 'request.approved',
                    'approved_for_execution' => 'request.approved_for_execution',
                    'execution_queued' => 'request.execution.queued',
                    'execution_processing' => 'request.execution.processing',
                    'settled' => 'request.execution.settled',
                    'failed' => 'request.execution.failed',
                    'reversed' => 'request.execution.reversed',
                    default => 'request.approved',
                },
                'reject' => 'request.rejected',
                default => 'request.returned',
            };

            $this->requestCommunicationLogger->log(
                request: $fresh,
                event: $requesterEvent,
                channels: $notificationChannels,
                recipientUserIds: [(int) $fresh->requested_by],
                requestApprovalId: $currentApproval?->id ? (int) $currentApproval->id : null,
                metadata: [
                    'request_code' => (string) $fresh->request_code,
                    'status' => (string) $fresh->status,
                    'scope' => $freshScope,
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
        $currentScope = $this->requestApprovalRouter->currentScope($request);

        $currentApproval = RequestApproval::query()
            ->where('company_id', (int) $request->company_id)
            ->where('request_id', (int) $request->id)
            ->where('scope', $currentScope)
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

    private function usesExecutionEnabledMode(SpendRequest $request): bool
    {
        $request->loadMissing('company.subscription');

        return (string) ($request->company?->subscription?->payment_execution_mode ?? '') === TenantExecutionModeService::MODE_EXECUTION_ENABLED;
    }

    private function startPaymentAuthorizationPhase(SpendRequest $request, User $user): bool
    {
        $metadata = array_merge((array) ($request->metadata ?? []), [
            'approval_scope' => RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION,
        ]);

        $probe = clone $request;
        $probe->setAttribute('metadata', $metadata);
        $probe->setAttribute('approved_amount', (int) ($request->approved_amount ?: $request->amount));

        $steps = $this->requestApprovalRouter->resolveApplicableSteps($probe);
        if ($steps->isEmpty()) {
            return false;
        }

        $firstStep = $steps->first();

        $request->forceFill([
            'status' => 'in_review',
            'current_approval_step' => (int) $firstStep->step_order,
            'approved_amount' => (int) ($request->approved_amount ?: $request->amount),
            'decision_note' => null,
            'decided_at' => null,
            'metadata' => array_merge($metadata, [
                'payment_authorization_started_at' => now()->toDateTimeString(),
            ]),
            'updated_by' => $user->id,
        ])->save();

        foreach ($steps as $step) {
            $isFirstStep = (int) $step->step_order === (int) $firstStep->step_order;
            $stepSla = $this->approvalTimingPolicyResolver->resolve(
                companyId: (int) $request->company_id,
                departmentId: $request->department_id ? (int) $request->department_id : null,
                stepLevelSla: (array) data_get((array) ($step->metadata ?? []), 'sla', [])
            );

            RequestApproval::query()->updateOrCreate(
                [
                    'company_id' => (int) $request->company_id,
                    'request_id' => (int) $request->id,
                    'scope' => RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION,
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
                        ? $this->requestApprovalSlaService->dueAtFromNow(['sla' => $stepSla])
                        : null,
                    'reminder_sent_at' => null,
                    'escalated_at' => null,
                    'reminder_count' => 0,
                    'comment' => null,
                    'from_status' => null,
                    'to_status' => null,
                    'metadata' => [
                        'actor_type' => $step->actor_type,
                        'actor_value' => $step->actor_value,
                        'notification_channels' => (array) ($step->notification_channels ?? []),
                        'sla' => $stepSla,
                    ],
                ]
            );
        }

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}