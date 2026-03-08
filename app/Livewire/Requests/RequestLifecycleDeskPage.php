<?php

namespace App\Livewire\Requests;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantModuleAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request Lifecycle Desk')]
class RequestLifecycleDeskPage extends Component
{
    private const LANE_LIMIT = 8;

    public bool $readyToLoad = false;

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $deepLinkSearch = trim((string) request()->query('search', ''));
        if ($deepLinkSearch !== '') {
            $this->search = mb_substr($deepLinkSearch, 0, 120);
        }
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 120);
    }

    public function render(TenantModuleAccessService $moduleAccessService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $requestsEnabled = $moduleAccessService->moduleEnabled($user, 'requests');

        $desk = $this->readyToLoad
            ? ($requestsEnabled
                ? $this->buildDeskData($user)
                : $this->emptyDeskData('Requests module is disabled for this tenant plan.'))
            : $this->emptyDeskData('Loading request lifecycle desk...');

        return view('livewire.requests.request-lifecycle-desk-page', [
            'desk' => $desk,
            'canOpenPayoutQueue' => $this->canOpenPayoutQueue($user),
        ]);
    }

    /**
     * @return array{
     *   enabled: bool,
     *   disabled_reason: ?string,
     *   summary: array<string, mixed>,
     *   lanes: array<string, array<int, array<string, mixed>>>
     * }
     */
    private function buildDeskData(User $user): array
    {
        $approvedNeedPo = $this->approvedNeedPoLane($user);
        $procurementFollowUp = $this->procurementFollowUpLane($user);
        $readyForDispatch = $this->readyForDispatchLane($user);
        $executionActive = $this->executionActiveLane($user);
        $closedOutcomes = $this->closedOutcomeLane($user);

        $workload = $this->buildWorkloadSummary([
            [
                'key' => 'approved_need_po',
                'label' => 'Approved (Need PO)',
                'count' => $approvedNeedPo['count'],
                'tone' => 'amber',
            ],
            [
                'key' => 'po_match_followup',
                'label' => 'PO / Match Follow-up',
                'count' => $procurementFollowUp['count'],
                'tone' => 'indigo',
            ],
            [
                'key' => 'waiting_dispatch',
                'label' => 'Waiting Payout Dispatch',
                'count' => $readyForDispatch['count'],
                'tone' => 'emerald',
            ],
            [
                'key' => 'execution_active_retry',
                'label' => 'Execution Active / Retry',
                'count' => $executionActive['count'],
                'tone' => 'rose',
            ],
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
    private function approvedNeedPoLane(User $user): array
    {
        $baseQuery = $this->baseRequestQuery($user)
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
                    'status' => 'Approved - Need PO',
                    'context' => 'Convert approved request to PO before procurement/match control can continue.',
                    'next_action_label' => 'Convert to PO',
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
    private function procurementFollowUpLane(User $user): array
    {
        $baseQuery = $this->baseRequestQuery($user)
            ->whereIn('status', ['approved', 'approved_for_execution'])
            ->has('purchaseOrders')
            ->with([
                'requester:id,name',
                'department:id,name',
                'purchaseOrders:id,company_id,spend_request_id,po_number,po_status,updated_at',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $rows = [];
        $count = 0;

        foreach ((clone $baseQuery)->cursor() as $request) {
            /** @var SpendRequest $request */
            if (! $this->needsProcurementFollowUp($request)) {
                continue;
            }

            $count++;
            if (count($rows) >= self::LANE_LIMIT) {
                continue;
            }

            $latestPo = $request->purchaseOrders
                ->sortByDesc(fn ($po) => (int) $po->id)
                ->first();

            $isBlocked = $this->isProcurementBlocked($request);
            $blockReason = trim((string) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.reason', ''));

            $rows[] = [
                'ref' => (string) $request->request_code,
                'title' => (string) $request->title,
                'meta' => sprintf(
                    'PO: %s | %s',
                    (string) ($latestPo?->po_number ?? 'N/A'),
                    (string) ($request->requester?->name ?? 'Requester')
                ),
                'status' => $isBlocked ? 'Exception to Resolve' : 'Waiting for Procurement Progress',
                'context' => $isBlocked
                    ? ($blockReason !== '' ? $blockReason : 'Procurement gate blocked payout. Resolve exceptions and retry.')
                    : 'PO exists but request is not yet payout-dispatch ready.',
                'next_action_label' => $isBlocked ? 'Resolve Exception' : 'Open Procurement',
                'next_action_url' => $isBlocked
                    ? route('procurement.match-exceptions', ['search' => (string) $request->request_code])
                    : route('procurement.release-desk', ['search' => (string) $request->request_code]),
                'next_action_tone' => $isBlocked ? 'rose' : 'indigo',
            ];
        }

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function readyForDispatchLane(User $user): array
    {
        $baseQuery = $this->baseRequestQuery($user)
            ->where('status', 'approved_for_execution')
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
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $rows = [];
        $count = 0;
        $canOpenPayoutQueue = $this->canOpenPayoutQueue($user);

        foreach ((clone $baseQuery)->cursor() as $request) {
            /** @var SpendRequest $request */
            if ($this->isProcurementBlocked($request)) {
                continue;
            }

            $count++;
            if (count($rows) >= self::LANE_LIMIT) {
                continue;
            }

            $finalApprover = $this->finalApproverName($request);

            $rows[] = [
                'ref' => (string) $request->request_code,
                'title' => (string) $request->title,
                'meta' => sprintf(
                    '%s | Final approver: %s',
                    (string) ($request->requester?->name ?? 'Requester'),
                    $finalApprover
                ),
                'status' => 'Ready for Payout Dispatch',
                'context' => $canOpenPayoutQueue
                    ? 'All approvals done. Dispatch payout from the execution queue.'
                    : 'All approvals done. Payout queue is restricted for your role. Open request to monitor status.',
                'next_action_label' => $canOpenPayoutQueue ? 'Run Payout' : 'Open Request',
                'next_action_url' => $canOpenPayoutQueue
                    ? route('execution.payout-ready', ['search' => (string) $request->request_code])
                    : route('requests.index', ['open_request_id' => (int) $request->id]),
                'next_action_tone' => $canOpenPayoutQueue ? 'emerald' : 'indigo',
            ];
        }

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function executionActiveLane(User $user): array
    {
        $baseQuery = $this->baseRequestQuery($user)
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
                        ? ($attemptError !== '' ? 'Failed: '.$attemptError : ($canOpenPayoutQueue
                            ? 'Failed execution. Check provider/config/state and rerun.'
                            : 'Failed execution. Payout rerun is restricted for your role; notify finance/owner.'))
                        : ($canOpenPayoutQueue
                            ? 'Execution is in progress; re-check queue for latest state.'
                            : 'Execution is in progress. Payout queue is restricted for your role; open request to monitor status.'),
                    'next_action_label' => $canOpenPayoutQueue
                        ? ($isFailed ? 'Rerun Payout' : 'Re-check Queue')
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
    private function closedOutcomeLane(User $user): array
    {
        $baseQuery = $this->baseRequestQuery($user)
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
                        ? 'Payout settled; request left waiting queue.'
                        : 'Payout reversed; review incident history if follow-up is required.',
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

    private function needsProcurementFollowUp(SpendRequest $request): bool
    {
        $status = (string) $request->status;
        if ($status === 'approved') {
            return true;
        }

        if ($status === 'approved_for_execution' && $this->isProcurementBlocked($request)) {
            return true;
        }

        return false;
    }

    private function isProcurementBlocked(SpendRequest $request): bool
    {
        return (bool) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.blocked', false);
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

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', SpendRequest::class);
    }

    private function baseRequestQuery(User $user): Builder
    {
        $query = SpendRequest::query()
            ->where('company_id', (int) $user->company_id);

        $this->applyRequestRoleScope($query, $user);

        if ($this->search !== '') {
            $search = $this->search;
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

    /**
     * @return array{
     *   enabled: bool,
     *   disabled_reason: ?string,
     *   summary: array<string, mixed>,
     *   lanes: array<string, array<int, array<string, mixed>>>
     * }
     */
    private function emptyDeskData(string $reason): array
    {
        return [
            'enabled' => false,
            'disabled_reason' => $reason,
            'summary' => [
                'approved_need_po' => 0,
                'po_match_followup' => 0,
                'waiting_dispatch' => 0,
                'execution_active_retry' => 0,
                'closed_outcomes' => 0,
                'workload_total' => 0,
                'bottleneck_label' => 'No blockers',
                'bottleneck_count' => 0,
                'segments' => [],
            ],
            'lanes' => [
                'approved_need_po' => [],
                'po_match_followup' => [],
                'waiting_dispatch' => [],
                'execution_active_retry' => [],
                'closed_outcomes' => [],
            ],
        ];
    }
}

