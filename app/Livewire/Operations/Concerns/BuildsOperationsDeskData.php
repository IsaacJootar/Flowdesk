<?php

namespace App\Livewire\Operations\Concerns;

use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait BuildsOperationsDeskData
{
    private const LANE_LIMIT = 8;

    /**
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,mixed>}
     */
    private function buildApprovalDesk(User $user, bool $requestsEnabled, string $search = ''): array
    {
        if (! $requestsEnabled) {
            return $this->emptyDeskData('Requests module is disabled for this tenant plan.');
        }

        $companyId = (int) $user->company_id;

        $pendingBase = RequestApproval::query()
            ->with([
                'request:id,company_id,request_code,title,status,department_id,requested_by,updated_at',
                'request.requester:id,name',
                'request.department:id,name',
            ])
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereNull('acted_at')
            ->whereHas('request', function (Builder $query) use ($user): void {
                $query->where('status', 'in_review');
                $this->applyRequestRoleScope($query, $user);
            });

        if ($search !== '') {
            $pendingBase->where(function (Builder $query) use ($search): void {
                $query->whereHas('request', function (Builder $requestQuery) use ($search): void {
                    $requestQuery
                        ->where('request_code', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhereHas('requester', fn (Builder $requesterQuery) => $requesterQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', '%'.$search.'%'));
                });
            });
        }

        $pendingCount = (int) (clone $pendingBase)->count();
        $overdueCount = (int) (clone $pendingBase)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $pendingRows = (clone $pendingBase)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get()
            ->map(fn (RequestApproval $approval): array => $this->mapPendingApprovalRow($approval))
            ->all();

        $overdueRows = (clone $pendingBase)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(self::LANE_LIMIT)
            ->get()
            ->map(fn (RequestApproval $approval): array => $this->mapPendingApprovalRow($approval))
            ->all();

        $returnedBase = SpendRequest::query()
            ->with([
                'requester:id,name',
                'department:id,name',
            ])
            ->where('company_id', $companyId)
            ->where('status', 'returned');

        $this->applyRequestRoleScope($returnedBase, $user);

        if ($search !== '') {
            $returnedBase->where(function (Builder $query) use ($search): void {
                $query
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('requester', fn (Builder $requesterQuery) => $requesterQuery->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $returnedCount = (int) (clone $returnedBase)->count();

        $returnedRows = (clone $returnedBase)
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get()
            ->map(function (SpendRequest $request): array {
                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) $request->title,
                    'meta' => sprintf(
                        '%s | %s',
                        (string) ($request->requester?->name ?? 'Requester'),
                        (string) ($request->department?->name ?? 'Department')
                    ),
                    'status' => 'Returned for update',
                    'context' => 'Requester needs to update and resubmit this request.',
                    'next_action_label' => 'Open Request',
                    'next_action_url' => route('requests.index', ['open_request_id' => (int) $request->id]),
                ];
            })
            ->all();

        $workload = $this->buildWorkloadSummary([
            ['key' => 'pending', 'label' => 'Pending My Approval', 'count' => $pendingCount, 'tone' => 'indigo'],
            ['key' => 'overdue', 'label' => 'Overdue / SLA', 'count' => $overdueCount, 'tone' => 'rose'],
            ['key' => 'returned', 'label' => 'Returned for Update', 'count' => $returnedCount, 'tone' => 'amber'],
        ]);

        return [
            'enabled' => true,
            'disabled_reason' => null,
            'summary' => [
                'pending_count' => $pendingCount,
                'overdue_count' => $overdueCount,
                'returned_count' => $returnedCount,
                ...$workload,
            ],
            'lanes' => [
                'pending' => $pendingRows,
                'overdue' => $overdueRows,
                'returned' => $returnedRows,
            ],
        ];
    }

    /**
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,mixed>}
     */
    private function buildPayablesDesk(User $user, bool $vendorsEnabled, bool $procurementEnabled, bool $requestsEnabled, string $search = ''): array
    {
        if (! $vendorsEnabled) {
            return $this->emptyDeskData('Vendors module is disabled for this tenant plan.');
        }

        $companyId = (int) $user->company_id;

        $openInvoicesBase = VendorInvoice::query()
            ->with('vendor:id,name')
            ->where('company_id', $companyId)
            ->where('outstanding_amount', '>', 0)
            ->whereIn('status', [
                VendorInvoice::STATUS_UNPAID,
                VendorInvoice::STATUS_PART_PAID,
            ]);

        if ($search !== '') {
            $openInvoicesBase->where(function (Builder $query) use ($search): void {
                $query
                    ->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $openInvoiceCount = (int) (clone $openInvoicesBase)->count();
        $openInvoiceRows = (clone $openInvoicesBase)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get()
            ->map(fn (VendorInvoice $invoice): array => $this->mapVendorInvoiceRow($invoice))
            ->all();

        $partPaidBase = VendorInvoice::query()
            ->with('vendor:id,name')
            ->where('company_id', $companyId)
            ->where('status', VendorInvoice::STATUS_PART_PAID)
            ->where('outstanding_amount', '>', 0);

        if ($search !== '') {
            $partPaidBase->where(function (Builder $query) use ($search): void {
                $query
                    ->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $partPaidCount = (int) (clone $partPaidBase)->count();
        $partPaidRows = (clone $partPaidBase)
            ->orderBy('due_date')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get()
            ->map(fn (VendorInvoice $invoice): array => $this->mapVendorInvoiceRow($invoice))
            ->all();

        $blocked = $procurementEnabled && $requestsEnabled
            ? $this->buildBlockedPayoutRows($user, $search)
            : ['count' => 0, 'rows' => []];

        $failedRetryBase = RequestPayoutExecutionAttempt::query()
            ->with('request:id,company_id,request_code,title,status')
            ->where('company_id', $companyId)
            ->where('execution_status', 'failed')
            ->whereHas('request', function (Builder $query) use ($user): void {
                $this->applyRequestRoleScope($query, $user);
            });

        if ($search !== '') {
            $failedRetryBase->whereHas('request', function (Builder $query) use ($search): void {
                $query
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%');
            });
        }

        $failedRetryCount = (int) (clone $failedRetryBase)->count();
        $failedRetryRows = (clone $failedRetryBase)
            ->latest('failed_at')
            ->latest('id')
            ->limit(self::LANE_LIMIT)
            ->get()
            ->map(function (RequestPayoutExecutionAttempt $attempt): array {
                $request = $attempt->request;

                return [
                    'ref' => (string) ($request?->request_code ?? 'N/A'),
                    'title' => (string) ($request?->title ?? 'Payout execution attempt'),
                    'meta' => sprintf(
                        '%s | Last error: %s',
                        strtoupper((string) ($attempt->currency_code ?? 'NGN')).' '.number_format((float) ($attempt->amount ?? 0), 2),
                        trim((string) ($attempt->error_message ?: 'Check provider/config/state and retry.'))
                    ),
                    'status' => 'Failed payout retry',
                    'context' => 'Retry payout after validating provider/config/state.',
                    'next_action_label' => 'Open Payout Queue',
                    'next_action_url' => route('execution.payout-ready', [
                        'search' => (string) ($request?->request_code ?? ''),
                    ]),
                ];
            })
            ->all();

        $workload = $this->buildWorkloadSummary([
            ['key' => 'open_invoices', 'label' => 'Open Invoices', 'count' => $openInvoiceCount, 'tone' => 'indigo'],
            ['key' => 'part_paid', 'label' => 'Part-Paid Invoices', 'count' => $partPaidCount, 'tone' => 'amber'],
            ['key' => 'blocked_handoff', 'label' => 'Blocked Payout Handoff', 'count' => (int) $blocked['count'], 'tone' => 'rose'],
            ['key' => 'failed_retry', 'label' => 'Failed Payout Retries', 'count' => $failedRetryCount, 'tone' => 'sky'],
        ]);

        return [
            'enabled' => true,
            'disabled_reason' => null,
            'summary' => [
                'open_invoice_count' => $openInvoiceCount,
                'part_paid_count' => $partPaidCount,
                'blocked_handoff_count' => (int) $blocked['count'],
                'failed_retry_count' => $failedRetryCount,
                ...$workload,
            ],
            'lanes' => [
                'open_invoices' => $openInvoiceRows,
                'part_paid' => $partPaidRows,
                'blocked_handoff' => (array) ($blocked['rows'] ?? []),
                'failed_retries' => $failedRetryRows,
            ],
        ];
    }

    /**
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,mixed>}
     */
    private function buildCloseDesk(User $user, bool $requestsEnabled, bool $procurementEnabled, bool $treasuryEnabled): array
    {
        $companyId = (int) $user->company_id;

        $unreconciledLines = $treasuryEnabled
            ? (int) BankStatementLine::query()
                ->where('company_id', $companyId)
                ->where('is_reconciled', false)
                ->count()
            : 0;

        $openProcurementExceptions = $procurementEnabled
            ? (int) InvoiceMatchException::query()
                ->where('company_id', $companyId)
                ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                ->count()
            : 0;

        $failedPayouts = $requestsEnabled
            ? (int) RequestPayoutExecutionAttempt::query()
                ->where('company_id', $companyId)
                ->where('execution_status', 'failed')
                ->count()
            : 0;

        // Audit flags represent control-denial and payout-block signals still requiring review.
        $auditFlags = (int) TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->where('event_at', '>=', now()->subDays(30))
            ->whereIn('action', [
                'tenant.execution.payout.blocked_by_procurement_match',
                'tenant.procurement.match.exception.action.denied',
                'tenant.treasury.exception.action.denied',
            ])
            ->count();

        $workload = $this->buildWorkloadSummary([
            ['key' => 'treasury_unreconciled', 'label' => 'Unreconciled Treasury Lines', 'count' => $unreconciledLines, 'tone' => 'amber'],
            ['key' => 'procurement_open', 'label' => 'Open Procurement Exceptions', 'count' => $openProcurementExceptions, 'tone' => 'rose'],
            ['key' => 'failed_payout', 'label' => 'Failed Payout Retries', 'count' => $failedPayouts, 'tone' => 'sky'],
            ['key' => 'audit_flags', 'label' => 'Audit Flags (30d)', 'count' => $auditFlags, 'tone' => 'indigo'],
        ]);

        $closeBlockers = $unreconciledLines + $openProcurementExceptions + $failedPayouts + $auditFlags;

        $checkRows = [
            [
                'label' => 'Treasury reconciliation backlog',
                'count' => $unreconciledLines,
                'status' => $unreconciledLines === 0 ? 'Ready' : 'Action needed',
                'note' => $treasuryEnabled
                    ? 'Unreconciled bank lines should be cleared or triaged before close.'
                    : 'Treasury module is disabled for this tenant plan.',
                'next_action_label' => $treasuryEnabled ? 'Open Treasury Desk' : null,
                'next_action_url' => $treasuryEnabled ? route('treasury.reconciliation') : null,
            ],
            [
                'label' => 'Procurement mismatch backlog',
                'count' => $openProcurementExceptions,
                'status' => $openProcurementExceptions === 0 ? 'Ready' : 'Action needed',
                'note' => $procurementEnabled
                    ? 'Open 3-way match exceptions should be resolved or waived with notes.'
                    : 'Procurement module is disabled for this tenant plan.',
                'next_action_label' => $procurementEnabled ? 'Open Procurement Desk' : null,
                'next_action_url' => $procurementEnabled ? route('procurement.release-desk') : null,
            ],
            [
                'label' => 'Failed payout retries',
                'count' => $failedPayouts,
                'status' => $failedPayouts === 0 ? 'Ready' : 'Action needed',
                'note' => $requestsEnabled
                    ? 'Failed payout attempts should be rerun or escalated before close.'
                    : 'Requests module is disabled for this tenant plan.',
                'next_action_label' => $requestsEnabled ? 'Open Payout Queue' : null,
                'next_action_url' => $requestsEnabled ? route('execution.payout-ready', ['status' => 'failed']) : null,
            ],
            [
                'label' => 'Control and audit flags (30d)',
                'count' => $auditFlags,
                'status' => $auditFlags === 0 ? 'Ready' : 'Review',
                'note' => 'Review denied sensitive actions and blocked payout handoffs in recent audit events.',
                'next_action_label' => 'Open Reports Center',
                'next_action_url' => route('reports.index'),
            ],
        ];

        return [
            'enabled' => true,
            'disabled_reason' => null,
            'summary' => [
                'unreconciled_lines' => $unreconciledLines,
                'open_procurement_exceptions' => $openProcurementExceptions,
                'failed_payouts' => $failedPayouts,
                'audit_flags' => $auditFlags,
                'close_status' => $closeBlockers === 0 ? 'Close Ready' : 'Action Needed',
                ...$workload,
            ],
            'lanes' => [
                'checks' => $checkRows,
            ],
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function buildBlockedPayoutRows(User $user, string $search = ''): array
    {
        $companyId = (int) $user->company_id;
        $rows = collect();
        $count = 0;
        $normalizedSearch = mb_strtolower(trim($search));

        $query = SpendRequest::query()
            ->with('requester:id,name')
            ->where('company_id', $companyId)
            ->whereIn('status', [
                'approved_for_execution',
                'execution_queued',
                'execution_processing',
                'failed',
            ]);

        $this->applyRequestRoleScope($query, $user);

        // Use chunking so blocked-hand-off count remains accurate without DB-specific JSON query syntax.
        $query
            ->orderBy('id')
            ->chunkById(200, function (Collection $chunk) use (&$count, &$rows, $normalizedSearch): void {
                foreach ($chunk as $request) {
                    if (! (bool) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.blocked', false)) {
                        continue;
                    }

                    $searchHaystack = mb_strtolower(trim(sprintf(
                        '%s %s %s',
                        (string) $request->request_code,
                        (string) $request->title,
                        (string) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.reason', '')
                    )));

                    if ($normalizedSearch !== '' && ! str_contains($searchHaystack, $normalizedSearch)) {
                        continue;
                    }

                    $count++;

                    if ($rows->count() >= self::LANE_LIMIT) {
                        continue;
                    }

                    $rows->push([
                        'ref' => (string) $request->request_code,
                        'title' => (string) $request->title,
                        'meta' => sprintf(
                            '%s | %s',
                            (string) ($request->requester?->name ?? 'Requester'),
                            strtoupper((string) ($request->currency ?: 'NGN')).' '.number_format((int) ($request->approved_amount ?: $request->amount ?: 0))
                        ),
                        'status' => 'Blocked payout handoff',
                        'context' => trim((string) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.reason', 'Resolve procurement mismatch blockers and retry payout queueing.')),
                        'next_action_label' => 'Open Procurement Desk',
                        'next_action_url' => route('procurement.release-desk'),
                    ]);
                }
            });

        return [
            'count' => $count,
            'rows' => $rows->all(),
        ];
    }

    /**
     * @param  array<int, array{key:string,label:string,count:int,tone:string}>  $segments
     * @return array{workload_total:int,bottleneck_label:string,bottleneck_count:int,segments:array<int,array{key:string,label:string,count:int,percent:float,tone:string}>}
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
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,mixed>}
     */
    private function emptyDeskData(string $reason): array
    {
        return [
            'enabled' => false,
            'disabled_reason' => $reason,
            'summary' => [
                'workload_total' => 0,
                'bottleneck_label' => 'No blockers',
                'bottleneck_count' => 0,
                'segments' => [],
            ],
            'lanes' => [],
        ];
    }

    private function mapPendingApprovalRow(RequestApproval $approval): array
    {
        $request = $approval->request;
        $isOverdue = $approval->due_at !== null && now()->greaterThan($approval->due_at);

        return [
            'ref' => (string) ($request?->request_code ?? 'N/A'),
            'title' => (string) ($request?->title ?? 'Approval item'),
            'meta' => sprintf(
                '%s | %s',
                (string) ($request?->requester?->name ?? 'Requester'),
                (string) ($request?->department?->name ?? 'Department')
            ),
            'status' => $isOverdue ? 'Overdue step' : 'Pending approval',
            'context' => $approval->due_at
                ? sprintf('Due at %s', $approval->due_at->format('M d, Y H:i'))
                : 'No due time set yet for this step.',
            'next_action_label' => 'Review Request',
            'next_action_url' => route('requests.index', ['open_request_id' => (int) ($request?->id ?? 0)]),
        ];
    }

    private function mapVendorInvoiceRow(VendorInvoice $invoice): array
    {
        $isOverdue = $invoice->due_date !== null
            && $invoice->outstanding_amount > 0
            && now()->startOfDay()->greaterThan($invoice->due_date);

        $status = $invoice->status === VendorInvoice::STATUS_PART_PAID
            ? 'Part-paid invoice'
            : ($isOverdue ? 'Overdue invoice' : 'Unpaid invoice');

        return [
            'ref' => (string) $invoice->invoice_number,
            'title' => (string) ($invoice->vendor?->name ?? 'Vendor invoice'),
            'meta' => sprintf(
                'Outstanding %s %s | Due %s',
                strtoupper((string) ($invoice->currency ?: 'NGN')),
                number_format((int) $invoice->outstanding_amount),
                $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A'
            ),
            'status' => $status,
            'context' => 'Invoice remains in payables lane until outstanding becomes zero.',
            'next_action_label' => 'Open Vendor',
            'next_action_url' => route('vendors.show', ['vendor' => (int) ($invoice->vendor_id ?? 0)]),
        ];
    }

    private function canAccessPage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
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
}
