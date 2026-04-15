<?php

namespace App\Services\Requests;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RequestLifecycleDeskService
{
    private const LANE_LIMIT = 8;

    /**
     * @return array{
     *   enabled: bool,
     *   disabled_reason: ?string,
     *   summary: array<string, mixed>,
     *   lanes: array<string, array<int, array<string, mixed>>>
     * }
     */
    public function buildDeskData(User $user, string $search = ''): array
    {
        $approvedNeedPo = $this->approvedNeedPoLane($user, $search);
        $procurementFollowUp = $this->procurementFollowUpLane($user, $search);
        $readyForDispatch = $this->readyForDispatchLane($user, $search);
        $executionActive = $this->executionActiveLane($user, $search);
        $closedOutcomes = $this->closedOutcomeLane($user, $search);

        $workload = $this->buildWorkloadSummary([
            ['key' => 'approved_need_po', 'label' => 'No PO Yet', 'count' => $approvedNeedPo['count'], 'tone' => 'amber'],
            ['key' => 'po_match_followup', 'label' => 'PO in Progress', 'count' => $procurementFollowUp['count'], 'tone' => 'indigo'],
            ['key' => 'waiting_dispatch', 'label' => 'Ready to Pay', 'count' => $readyForDispatch['count'], 'tone' => 'emerald'],
            ['key' => 'execution_active_retry', 'label' => 'Payment In Progress', 'count' => $executionActive['count'], 'tone' => 'rose'],
        ]);

        return [
            'enabled' => true,
            'disabled_reason' => null,
            'summary' => [
                'approved_need_po' => $approvedNeedPo['count'],
                'po_match_followup' => $procurementFollowUp['count'],
                'waiting_dispatch' => $readyForDispatch['count'],
                'execution_active_retry' => $executionActive['count'],
                'closed_outcomes' => $closedOutcomes['count'],
                ...$workload,
            ],
            'lanes' => [
                'approved_need_po' => $approvedNeedPo['rows'],
                'po_match_followup' => $procurementFollowUp['rows'],
                'waiting_dispatch' => $readyForDispatch['rows'],
                'execution_active_retry' => $executionActive['rows'],
                'closed_outcomes' => $closedOutcomes['rows'],
            ],
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function approvedNeedPoLane(User $user, string $search): array
    {
        $baseQuery = $this->baseRequestQuery($user, $search)
            ->where('status', 'approved')
            ->doesntHave('purchaseOrders');

        $count = (int) (clone $baseQuery)->count();

        $rows = (clone $baseQuery)
            ->with([
                'requester:id,name',
                'department:id,name',
            ])
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get([
                'id',
                'company_id',
                'request_code',
                'title',
                'status',
                'requested_by',
                'department_id',
                'amount',
                'approved_amount',
                'currency',
            ])
            ->map(function (SpendRequest $request): array {
                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'meta' => sprintf(
                        '%s | %s | %s %s',
                        (string) ($request->requester?->name ?? 'Requester'),
                        (string) ($request->department?->name ?? 'Department'),
                        strtoupper((string) ($request->currency ?: 'NGN')),
                        number_format((int) ($request->approved_amount ?: $request->amount ?: 0))
                    ),
                    'status' => 'Approved — No PO Yet',
                    'context' => 'Create a Purchase Order to move this request forward.',
                    'next_action_label' => 'Create Purchase Order',
                    'next_action_url' => route('requests.index', ['open_request_id' => (int) $request->id]),
                    'next_action_tone' => 'amber',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function procurementFollowUpLane(User $user, string $search): array
    {
        $baseQuery = $this->baseRequestQuery($user, $search)
            ->whereIn('status', ['approved', 'approved_for_execution'])
            ->has('purchaseOrders')
            ->where(function (Builder $query): void {
                $query->where('status', 'approved')
                    ->orWhere(function (Builder $blockedQuery): void {
                        $this->applyBlockedMetadataFilter($blockedQuery);
                    });
            });

        $count = (int) (clone $baseQuery)->count();

        $rows = (clone $baseQuery)
            ->with([
                'requester:id,name',
                'purchaseOrders:id,company_id,spend_request_id,po_number,po_status,updated_at',
            ])
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get([
                'id',
                'company_id',
                'request_code',
                'title',
                'status',
                'requested_by',
                'metadata',
                'updated_at',
            ])
            ->map(function (SpendRequest $request): array {
                $latestPo = $request->purchaseOrders
                    ->sortByDesc(fn ($po) => (int) $po->id)
                    ->first();

                $isBlocked = $this->isProcurementBlocked($request);
                $blockReason = trim((string) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.reason', ''));

                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'meta' => sprintf(
                        'PO: %s | %s',
                        (string) ($latestPo?->po_number ?? 'N/A'),
                        (string) ($request->requester?->name ?? 'Requester')
                    ),
                    'status' => $isBlocked ? 'Invoice Mismatch' : 'Purchase Order in Progress',
                    'context' => $isBlocked
                        ? ($blockReason !== '' ? $blockReason : 'An invoice mismatch is blocking payment. Fix it to continue.')
                        : 'Purchase Order exists. Waiting for delivery confirmation and invoice match.',
                    'next_action_label' => $isBlocked ? 'Fix Mismatch' : 'View Purchase Order',
                    'next_action_url' => $isBlocked
                        ? route('procurement.match-exceptions', ['search' => (string) $request->request_code])
                        : route('procurement.release-desk', ['search' => (string) $request->request_code]),
                    'next_action_tone' => $isBlocked ? 'rose' : 'indigo',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function readyForDispatchLane(User $user, string $search): array
    {
        $baseQuery = $this->baseRequestQuery($user, $search)
            ->where('status', 'approved_for_execution');

        $blockedCount = (int) (clone $baseQuery)
            ->where(function (Builder $query): void {
                $this->applyBlockedMetadataFilter($query);
            })
            ->count();

        $count = max(0, (int) ((clone $baseQuery)->count() - $blockedCount));
        $canOpenPayoutQueue = $this->canOpenPayoutQueue($user);

        $rows = (clone $baseQuery)
            ->with([
                'requester:id,name',
                // Latest approved audit row is used as the final approver badge for this request.
                'approvals' => function (Builder $approvalQuery): void {
                    $approvalQuery
                        ->where('action', 'approved')
                        ->whereNotNull('acted_by')
                        ->with('actor:id,name')
                        ->latest('acted_at')
                        ->latest('id');
                },
                'payoutExecutionAttempt:id,company_id,request_id,execution_status,error_message,updated_at',
            ])
            ->latest('updated_at')
            ->latest('id')
            // Pull a slightly wider slice so blocked rows can be discarded without scanning the tenant's full request history.
            ->limit(self::LANE_LIMIT * 5)
            ->get([
                'id',
                'company_id',
                'request_code',
                'title',
                'status',
                'requested_by',
                'currency',
                'amount',
                'approved_amount',
                'metadata',
                'updated_at',
            ])
            ->filter(fn (SpendRequest $request): bool => ! $this->isProcurementBlocked($request))
            ->take(self::LANE_LIMIT)
            ->map(function (SpendRequest $request) use ($canOpenPayoutQueue): array {
                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'meta' => sprintf(
                        '%s | Final approver: %s',
                        (string) ($request->requester?->name ?? 'Requester'),
                        $this->finalApproverName($request)
                    ),
                    'status' => 'Ready to Pay',
                    'context' => $canOpenPayoutQueue
                        ? 'All approvals done. Send the payment from the payments queue.'
                        : 'All approvals done. Your role cannot send payments — open the request to monitor progress.',
                    'next_action_label' => $canOpenPayoutQueue ? 'Send Payment' : 'Open Request',
                    'next_action_url' => $canOpenPayoutQueue
                        ? route('execution.payout-ready', ['search' => (string) $request->request_code])
                        : route('requests.index', ['open_request_id' => (int) $request->id]),
                    'next_action_tone' => $canOpenPayoutQueue ? 'emerald' : 'indigo',
                ];
            })
            ->values()
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function executionActiveLane(User $user, string $search): array
    {
        $baseQuery = $this->baseRequestQuery($user, $search)
            ->whereIn('status', ['execution_queued', 'execution_processing', 'failed'])
            ->with([
                'requester:id,name',
                'payoutExecutionAttempt:id,company_id,request_id,execution_status,error_message,error_code,updated_at',
            ]);

        $count = (int) (clone $baseQuery)->count();
        $canOpenPayoutQueue = $this->canOpenPayoutQueue($user);

        $rows = (clone $baseQuery)
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get([
                'id',
                'company_id',
                'request_code',
                'title',
                'status',
                'requested_by',
                'currency',
                'amount',
                'approved_amount',
                'metadata',
                'updated_at',
            ])
            ->map(function (SpendRequest $request) use ($canOpenPayoutQueue): array {
                $attempt = $request->payoutExecutionAttempt;
                $attemptStatus = (string) ($attempt?->execution_status ?: $request->status);
                $attemptError = trim((string) ($attempt?->error_message ?: ''));
                $isFailed = (string) $request->status === 'failed' || $attemptStatus === 'failed';

                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'meta' => sprintf(
                        '%s | %s %s',
                        (string) ($request->requester?->name ?? 'Requester'),
                        strtoupper((string) ($request->currency ?: 'NGN')),
                        number_format((int) ($request->approved_amount ?: $request->amount ?: 0))
                    ),
                    'status' => ucwords(str_replace('_', ' ', (string) $request->status)),
                    'context' => $isFailed
                        ? ($attemptError !== '' ? 'Payment failed: '.$attemptError : ($canOpenPayoutQueue
                            ? 'Payment failed. Review the error and retry.'
                            : 'Payment failed. Your role cannot retry — notify your finance team.'))
                        : ($canOpenPayoutQueue
                            ? 'Payment is being processed. Check the queue for the latest status.'
                            : 'Payment is being processed. Your role cannot send payments — open the request to monitor progress.'),
                    'next_action_label' => $canOpenPayoutQueue
                        ? ($isFailed ? 'Retry Payment' : 'Check Queue')
                        : 'Open Request',
                    'next_action_url' => $canOpenPayoutQueue
                        ? route('execution.payout-ready', ['search' => (string) $request->request_code])
                        : route('requests.index', ['open_request_id' => (int) $request->id]),
                    'next_action_tone' => $canOpenPayoutQueue
                        ? ($isFailed ? 'rose' : 'sky')
                        : 'indigo',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function closedOutcomeLane(User $user, string $search): array
    {
        $baseQuery = $this->baseRequestQuery($user, $search)
            ->whereIn('status', ['settled', 'reversed'])
            ->with([
                'requester:id,name',
            ]);

        $count = (int) (clone $baseQuery)->count();

        $rows = (clone $baseQuery)
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get([
                'id',
                'company_id',
                'request_code',
                'title',
                'status',
                'requested_by',
                'currency',
                'amount',
                'approved_amount',
                'updated_at',
            ])
            ->map(function (SpendRequest $request): array {
                $status = (string) $request->status;

                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'meta' => sprintf(
                        '%s | %s %s',
                        (string) ($request->requester?->name ?? 'Requester'),
                        strtoupper((string) ($request->currency ?: 'NGN')),
                        number_format((int) ($request->approved_amount ?: $request->amount ?: 0))
                    ),
                    'status' => ucwords(str_replace('_', ' ', $status)),
                    'context' => $status === 'settled'
                        ? 'Payment was sent successfully.'
                        : 'Payment was reversed. Open the request for details.',
                    'next_action_label' => 'Open Request',
                    'next_action_url' => route('requests.index', ['open_request_id' => (int) $request->id]),
                    'next_action_tone' => $status === 'settled' ? 'emerald' : 'amber',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private function finalApproverName(SpendRequest $request): string
    {
        $approval = $request->approvals->first();
        if (! $approval instanceof RequestApproval) {
            return '-';
        }

        return (string) ($approval->actor?->name ?: 'System');
    }

    private function canOpenPayoutQueue(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function baseRequestQuery(User $user, string $search): Builder
    {
        $query = SpendRequest::query()
            ->where('company_id', (int) $user->company_id);

        $this->applyRequestRoleScope($query, $user);

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('requester', fn (Builder $requesterQuery) => $requesterQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query;
    }

    private function applyRequestRoleScope(Builder $query, User $user): Builder
    {
        $role = (string) $user->role;

        if (in_array($role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        if ($role === UserRole::Manager->value) {
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

    private function isProcurementBlocked(SpendRequest $request): bool
    {
        return (bool) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.blocked', false);
    }

    private function applyBlockedMetadataFilter(Builder $query): void
    {
        // Keep the JSON boolean filter permissive because sqlite and mysql serialize booleans differently.
        $query->where(function (Builder $blockedQuery): void {
            $blockedQuery
                ->where('metadata->execution->procurement_gate->blocked', true)
                ->orWhere('metadata->execution->procurement_gate->blocked', 1)
                ->orWhere('metadata->execution->procurement_gate->blocked', 'true');
        });
    }

    /**
     * @param  array<int, array{key:string,label:string,count:int,tone:string}>  $segments
     * @return array{
     *   workload_total:int,
     *   bottleneck_label:string,
     *   bottleneck_count:int,
     *   segments:array<int,array{key:string,label:string,count:int,percent:float,tone:string}>
     * }
     */
    private function buildWorkloadSummary(array $segments): array
    {
        $workloadTotal = array_sum(array_map(static fn (array $segment): int => (int) ($segment['count'] ?? 0), $segments));

        $bottleneckLabel = 'No blockers';
        $bottleneckCount = 0;

        $normalizedSegments = array_map(function (array $segment) use ($workloadTotal, &$bottleneckLabel, &$bottleneckCount): array {
            $count = (int) ($segment['count'] ?? 0);
            $percent = $workloadTotal > 0
                ? round(($count / $workloadTotal) * 100, 2)
                : 0.0;

            if ($count > $bottleneckCount) {
                $bottleneckCount = $count;
                $bottleneckLabel = (string) ($segment['label'] ?? 'No blockers');
            }

            return [
                'key' => (string) ($segment['key'] ?? 'segment'),
                'label' => (string) ($segment['label'] ?? 'Segment'),
                'count' => $count,
                'percent' => $percent,
                'tone' => (string) ($segment['tone'] ?? 'slate'),
            ];
        }, $segments);

        return [
            'workload_total' => $workloadTotal,
            'bottleneck_label' => $bottleneckLabel,
            'bottleneck_count' => $bottleneckCount,
            'segments' => $normalizedSegments,
        ];
    }
}
