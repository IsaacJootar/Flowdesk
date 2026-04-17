<?php

namespace App\Actions\Requests;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Requests\Models\CompanyRequestPolicySetting;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Services\ApprovalTimingPolicyResolver;
use App\Services\ActivityLogger;
use App\Services\RequestApprovalSlaService;
use App\Services\RequestBudgetGuardrail;
use App\Services\RequestCommunicationLogger;
use App\Services\RequestDuplicateDetector;
use App\Services\RequestApprovalRouter;
use App\Services\SpendLifecycleControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubmitSpendRequest
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RequestApprovalRouter $requestApprovalRouter,
        private readonly RequestCommunicationLogger $requestCommunicationLogger,
        private readonly RequestApprovalSlaService $requestApprovalSlaService,
        private readonly ApprovalTimingPolicyResolver $approvalTimingPolicyResolver,
        private readonly RequestBudgetGuardrail $requestBudgetGuardrail,
        private readonly RequestDuplicateDetector $requestDuplicateDetector,
        private readonly SpendLifecycleControlService $spendLifecycleControlService
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

        if ($this->requiresAttachments($request) && ! $request->attachments()->exists()) {
            throw ValidationException::withMessages([
                'attachments' => 'This request type requires at least one attachment before submission.',
            ]);
        }

        $workflow = $this->requestApprovalRouter->resolveActiveWorkflow($request);
        $steps = $this->requestApprovalRouter->resolveApplicableSteps($request);

        if (! $workflow || $steps->isEmpty()) {
            throw ValidationException::withMessages([
                'workflow' => 'No active approval workflow step applies to this request amount.',
            ]);
        }

        $firstStep = $steps->first();
        $policy = CompanyRequestPolicySetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $request->company_id],
                array_merge(
                    CompanyRequestPolicySetting::defaultAttributes(),
                    ['created_by' => $user->id, 'updated_by' => $user->id]
                )
            );
        $existingMetadata = (array) ($request->metadata ?? []);
        $effectiveDate = ! empty($existingMetadata['needed_by']) ? (string) $existingMetadata['needed_by'] : null;
        $budgetGuardrail = $this->requestBudgetGuardrail->evaluate(
            companyId: (int) $request->company_id,
            departmentId: (int) $request->department_id,
            incomingAmount: (int) $request->amount,
            effectiveDate: $effectiveDate
        );

        // Policy checks are advisory/blocking gates before workflow rows are generated.
        $policyWarnings = [];
        if ($budgetGuardrail['has_budget'] && $budgetGuardrail['is_exceeded']) {
            if ((string) $policy->budget_guardrail_mode === CompanyRequestPolicySetting::BUDGET_MODE_BLOCK) {
                $this->activityLogger->log(
                    action: 'request.budget.blocked',
                    entityType: SpendRequest::class,
                    entityId: (int) $request->id,
                    metadata: [
                        'request_code' => (string) $request->request_code,
                        'over_amount' => (int) $budgetGuardrail['over_amount'],
                        'guardrail' => $budgetGuardrail,
                    ],
                    companyId: (int) $request->company_id,
                    userId: $user->id,
                );

                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Request exceeds department budget by NGN %s for this period.',
                        number_format((int) $budgetGuardrail['over_amount'])
                    ),
                ]);
            }

            if ((string) $policy->budget_guardrail_mode === CompanyRequestPolicySetting::BUDGET_MODE_WARN) {
                $policyWarnings[] = sprintf(
                    'Budget warning: projected spend exceeds budget by NGN %s.',
                    number_format((int) $budgetGuardrail['over_amount'])
                );
            }
        }
        $budgetDecision = $this->spendLifecycleControlService->budgetDecision(
            companyId: (int) $request->company_id,
            guardrail: $budgetGuardrail,
            context: 'request_submission',
        );
        if (! $budgetDecision['allowed']) {
            $this->activityLogger->log(
                action: 'request.budget.blocked',
                entityType: SpendRequest::class,
                entityId: (int) $request->id,
                metadata: [
                    'request_code' => (string) $request->request_code,
                    'guardrail' => $budgetGuardrail,
                    'budget_decision' => $budgetDecision,
                ],
                companyId: (int) $request->company_id,
                userId: $user->id,
            );

            throw ValidationException::withMessages([
                'amount' => (string) $budgetDecision['message'],
            ]);
        }

        if ($budgetDecision['message'] !== '' && ! in_array((string) $budgetDecision['message'], $policyWarnings, true)) {
            $policyWarnings[] = (string) $budgetDecision['message'];
        }

        $duplicateAnalysis = [
            'risk' => 'none',
            'matches' => [],
        ];
        if ((bool) $policy->duplicate_detection_enabled) {
            $duplicateAnalysis = $this->requestDuplicateDetector->analyze(
                companyId: (int) $request->company_id,
                input: [
                    'requested_by' => (int) $request->requested_by,
                    'department_id' => (int) $request->department_id,
                    'vendor_id' => $request->vendor_id ? (int) $request->vendor_id : null,
                    'title' => (string) $request->title,
                    'amount' => (int) $request->amount,
                ],
                excludeRequestId: (int) $request->id,
                windowDays: (int) $policy->duplicate_window_days
            );

            if (($duplicateAnalysis['risk'] ?? 'none') !== 'none') {
                $policyWarnings[] = sprintf(
                    'Possible duplicate request found (%d match%s in last %d day%s).',
                    count((array) ($duplicateAnalysis['matches'] ?? [])),
                    count((array) ($duplicateAnalysis['matches'] ?? [])) === 1 ? '' : 'es',
                    (int) $policy->duplicate_window_days,
                    (int) $policy->duplicate_window_days === 1 ? '' : 's'
                );
            }
        }

        $policyChecks = [
            'budget' => array_merge($budgetGuardrail, [
                'mode' => (string) $policy->budget_guardrail_mode,
                'lifecycle_decision' => $budgetDecision,
            ]),
            'duplicate' => [
                'enabled' => (bool) $policy->duplicate_detection_enabled,
                'window_days' => (int) $policy->duplicate_window_days,
                'risk' => (string) ($duplicateAnalysis['risk'] ?? 'none'),
                'matches_count' => count((array) ($duplicateAnalysis['matches'] ?? [])),
                'matches' => (array) ($duplicateAnalysis['matches'] ?? []),
            ],
        ];

        if (($budgetDecision['message'] !== '' && $budgetDecision['severity'] !== 'none') || ($budgetGuardrail['has_budget'] && $budgetGuardrail['is_exceeded'] && (string) $policy->budget_guardrail_mode === CompanyRequestPolicySetting::BUDGET_MODE_WARN)) {
            $this->activityLogger->log(
                action: 'request.budget.warning',
                entityType: SpendRequest::class,
                entityId: (int) $request->id,
                metadata: [
                    'request_code' => (string) $request->request_code,
                    'guardrail' => $budgetGuardrail,
                    'budget_decision' => $budgetDecision,
                ],
                companyId: (int) $request->company_id,
                userId: $user->id,
            );
        }

        if (($duplicateAnalysis['risk'] ?? 'none') !== 'none') {
            $this->activityLogger->log(
                action: 'request.duplicate.warning',
                entityType: SpendRequest::class,
                entityId: (int) $request->id,
                metadata: [
                    'request_code' => (string) $request->request_code,
                    'duplicate' => $policyChecks['duplicate'],
                ],
                companyId: (int) $request->company_id,
                userId: $user->id,
            );
        }

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

        DB::transaction(function () use ($user, $request, $workflow, $steps, $firstStep, $organizationChannels, $requestChannelMode, $requestChannelOverride, $policyWarnings, $policyChecks): void {
            // New submission always starts from request-approval scope.
            RequestApproval::query()
                ->where('company_id', (int) $request->company_id)
                ->where('request_id', (int) $request->id)
                ->where('scope', RequestApprovalRouter::SCOPE_PAYMENT_AUTHORIZATION)
                ->delete();

            // Persist request state and approval rows atomically to avoid half-submitted records.
            $metadata = array_merge((array) ($request->metadata ?? []), [
                'approval_scope' => RequestApprovalRouter::SCOPE_REQUEST,
                'channel_mode' => $requestChannelMode,
                'notification_channels' => $requestChannelOverride,
                'policy_warnings' => array_values($policyWarnings),
                'policy_checks' => $policyChecks,
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
                    // Persist resolved timing snapshot so in-flight steps stay stable after future policy edits.
                    'sla' => $this->approvalTimingPolicyResolver->resolve(
                        companyId: (int) $request->company_id,
                        departmentId: $request->department_id ? (int) $request->department_id : null,
                        stepLevelSla: (array) data_get((array) ($step->metadata ?? []), 'sla', [])
                    ),
                ];

                RequestApproval::query()->updateOrCreate(
                    [
                        'company_id' => (int) $request->company_id,
                        'request_id' => $request->id,
                        'scope' => RequestApprovalRouter::SCOPE_REQUEST,
                        'step_order' => (int) $step->step_order,
                    ],
                    [
                        'workflow_step_id' => $step->id,
                        'step_key' => $step->step_key,
                        // Only the first applicable step starts pending; later steps wait in queued state.
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
                'policy_warnings_count' => count($policyWarnings),
                'policy_checks' => $policyChecks,
            ],
            companyId: (int) $request->company_id,
            userId: $user->id,
        );

        $fresh = $request->fresh(['workflow', 'items', 'approvals.workflowStep']) ?? $request;
        $firstApproval = $fresh->approvals
            ->first(fn (RequestApproval $approval): bool =>
                (string) ($approval->scope ?: RequestApprovalRouter::SCOPE_REQUEST) === RequestApprovalRouter::SCOPE_REQUEST
                && (int) $approval->step_order === (int) $firstStep->step_order
            );
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

    private function requiresAttachments(SpendRequest $request): bool
    {
        $metadata = (array) ($request->metadata ?? []);
        $typeCode = trim((string) ($metadata['request_type_code'] ?? $metadata['type'] ?? ''));
        if ($typeCode === '') {
            return false;
        }

        $type = CompanyRequestType::query()
            ->where('company_id', (int) $request->company_id)
            ->where('code', strtolower($typeCode))
            ->where('is_active', true)
            ->first();

        return (bool) ($type?->requires_attachments ?? false);
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
