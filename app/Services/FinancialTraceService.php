<?php

namespace App\Services;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\PaymentRunItem;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Treasury\Models\ReconciliationMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinancialTraceService
{
    /**
     * @return array<string,mixed>|null
     */
    public function buildForRequestId(int $companyId, int $requestId): ?array
    {
        /** @var SpendRequest|null $request */
        $request = SpendRequest::query()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->with([
                'requester:id,name',
                'department:id,name',
                'vendor:id,name',
                'approvals.actor:id,name',
                'approvals.workflowStep:id,step_order,step_key,actor_type,actor_value',
                'purchaseOrders.commitments',
                'purchaseOrders.matchResults',
                'expenses',
                'payoutExecutionAttempt',
            ])
            ->find($requestId);

        if (! $request instanceof SpendRequest) {
            return null;
        }

        return $this->buildForRequest($request);
    }

    /**
     * @return array<string,mixed>
     */
    public function buildForRequest(SpendRequest $request): array
    {
        $companyId = (int) $request->company_id;
        $request->loadMissing([
            'requester:id,name',
            'department:id,name',
            'vendor:id,name',
            'approvals.actor:id,name',
            'approvals.workflowStep:id,step_order,step_key,actor_type,actor_value',
            'purchaseOrders.commitments',
            'purchaseOrders.matchResults',
            'expenses',
            'payoutExecutionAttempt',
        ]);

        $budget = $this->budgetTrace($request);
        $approvals = $this->approvalTrace($request);
        $procurement = $this->procurementTrace($request);
        $payment = $this->paymentTrace($request);
        $expenses = $this->expenseTrace($request);
        $reconciliation = $this->reconciliationTrace(
            companyId: $companyId,
            payoutAttempt: $request->payoutExecutionAttempt,
            expenses: $request->expenses
        );
        $audit = $this->auditTrace(
            request: $request,
            payoutAttempt: $request->payoutExecutionAttempt,
            purchaseOrders: $request->purchaseOrders,
            expenses: $request->expenses
        );
        $gaps = $this->gaps($budget, $procurement, $payment, $expenses, $reconciliation);

        return [
            'request' => $this->requestTrace($request),
            'budget' => $budget,
            'approvals' => $approvals,
            'procurement' => $procurement,
            'payment' => $payment,
            'expenses' => $expenses,
            'reconciliation' => $reconciliation,
            'audit' => $audit,
            'timeline' => $this->timeline($request, $budget, $approvals, $procurement, $payment, $expenses, $reconciliation, $audit),
            'gaps' => $gaps,
            'completion' => $this->completionStatus($request, $budget, $approvals, $procurement, $payment, $expenses, $reconciliation, $gaps),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function requestTrace(SpendRequest $request): array
    {
        return [
            'id' => (int) $request->id,
            'request_code' => (string) $request->request_code,
            'title' => (string) $request->title,
            'status' => (string) $request->status,
            'amount' => (int) $request->amount,
            'approved_amount' => $request->approved_amount !== null ? (int) $request->approved_amount : null,
            'paid_amount' => (int) ($request->paid_amount ?? 0),
            'currency' => strtoupper((string) ($request->currency ?: 'NGN')),
            'requester' => (string) ($request->requester?->name ?? ''),
            'department' => (string) ($request->department?->name ?? ''),
            'vendor' => (string) ($request->vendor?->name ?? ''),
            'submitted_at' => $this->dateString($request->submitted_at),
            'decided_at' => $this->dateString($request->decided_at),
            'created_at' => $this->dateString($request->created_at),
            'updated_at' => $this->dateString($request->updated_at),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function budgetTrace(SpendRequest $request): array
    {
        $metadata = (array) ($request->metadata ?? []);
        $budgetCheck = (array) data_get($metadata, 'policy_checks.budget', []);
        $budgetId = (int) data_get($budgetCheck, 'budget_id', 0);
        $source = $budgetCheck !== [] ? 'request_policy_check' : 'active_budget_lookup';

        $budget = null;
        if ($budgetId > 0) {
            $budget = DepartmentBudget::query()
                ->withTrashed()
                ->where('company_id', (int) $request->company_id)
                ->find($budgetId);
        }

        if (! $budget instanceof DepartmentBudget) {
            $referenceDate = $this->referenceBudgetDate($request);
            $budget = DepartmentBudget::query()
                ->withTrashed()
                ->where('company_id', (int) $request->company_id)
                ->where('department_id', (int) $request->department_id)
                ->whereDate('period_start', '<=', $referenceDate)
                ->whereDate('period_end', '>=', $referenceDate)
                ->latest('period_start')
                ->first();
        }

        $hasBudget = (bool) ($budgetCheck['has_budget'] ?? $budget instanceof DepartmentBudget);
        $isExceeded = (bool) ($budgetCheck['is_exceeded'] ?? false);

        return [
            'source' => $source,
            'has_budget' => $hasBudget,
            'budget_id' => $budgetId > 0 ? $budgetId : ($budget instanceof DepartmentBudget ? (int) $budget->id : null),
            'department_id' => (int) $request->department_id,
            'period_start' => $this->dateString($budget?->period_start),
            'period_end' => $this->dateString($budget?->period_end),
            'allocated_amount' => (int) ($budgetCheck['allocated_amount'] ?? $budget?->allocated_amount ?? 0),
            'spent_amount' => (int) ($budgetCheck['spent_amount'] ?? $budget?->used_amount ?? 0),
            'projected_amount' => (int) ($budgetCheck['projected_amount'] ?? $request->amount ?? 0),
            'remaining_amount' => (int) ($budgetCheck['remaining_amount'] ?? $budget?->remaining_amount ?? 0),
            'over_amount' => (int) ($budgetCheck['over_amount'] ?? 0),
            'mode' => (string) ($budgetCheck['mode'] ?? ''),
            'is_exceeded' => $isExceeded,
            'status' => $hasBudget ? ($isExceeded ? 'over_budget' : 'within_budget') : 'no_budget_found',
            'effective_date' => (string) ($budgetCheck['effective_date'] ?? $this->referenceBudgetDate($request)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function approvalTrace(SpendRequest $request): array
    {
        return $request->approvals
            ->sortBy([
                ['scope', 'asc'],
                ['step_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($approval): array => [
                'id' => (int) $approval->id,
                'scope' => (string) ($approval->scope ?: 'request'),
                'step_order' => (int) $approval->step_order,
                'step_key' => (string) ($approval->step_key ?: $approval->workflowStep?->step_key ?: ''),
                'status' => (string) $approval->status,
                'action' => $approval->action ? (string) $approval->action : null,
                'actor' => (string) ($approval->actor?->name ?? ''),
                'acted_at' => $this->dateString($approval->acted_at),
                'due_at' => $this->dateString($approval->due_at),
                'from_status' => (string) ($approval->from_status ?? ''),
                'to_status' => (string) ($approval->to_status ?? ''),
                'comment' => (string) ($approval->comment ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function procurementTrace(SpendRequest $request): array
    {
        $purchaseOrders = $request->purchaseOrders->sortByDesc('id')->values();
        $commitments = $purchaseOrders->flatMap(fn (PurchaseOrder $order) => $order->commitments)->values();
        $matchResults = $purchaseOrders->flatMap(fn (PurchaseOrder $order) => $order->matchResults)->values();

        return [
            'purchase_orders' => $purchaseOrders
                ->map(fn (PurchaseOrder $order): array => [
                    'id' => (int) $order->id,
                    'po_number' => (string) $order->po_number,
                    'status' => (string) $order->po_status,
                    'budget_id' => $order->department_budget_id ? (int) $order->department_budget_id : null,
                    'amount' => (int) $order->total_amount,
                    'currency' => strtoupper((string) ($order->currency_code ?: 'NGN')),
                    'issued_at' => $this->dateString($order->issued_at),
                    'created_at' => $this->dateString($order->created_at),
                ])
                ->all(),
            'commitments' => $commitments
                ->map(fn (ProcurementCommitment $commitment): array => [
                    'id' => (int) $commitment->id,
                    'purchase_order_id' => (int) ($commitment->purchase_order_id ?? 0),
                    'budget_id' => $commitment->department_budget_id ? (int) $commitment->department_budget_id : null,
                    'status' => (string) $commitment->commitment_status,
                    'amount' => (int) $commitment->amount,
                    'currency' => strtoupper((string) ($commitment->currency_code ?: 'NGN')),
                    'effective_at' => $this->dateString($commitment->effective_at),
                    'released_at' => $this->dateString($commitment->released_at),
                ])
                ->all(),
            'match_results' => $matchResults
                ->map(fn ($result): array => [
                    'id' => (int) $result->id,
                    'purchase_order_id' => (int) $result->purchase_order_id,
                    'vendor_invoice_id' => $result->vendor_invoice_id ? (int) $result->vendor_invoice_id : null,
                    'status' => (string) $result->match_status,
                    'score' => $result->match_score !== null ? (float) $result->match_score : null,
                    'mismatch_reason' => (string) ($result->mismatch_reason ?? ''),
                    'matched_at' => $this->dateString($result->matched_at),
                    'resolved_at' => $this->dateString($result->resolved_at),
                ])
                ->all(),
            'summary' => [
                'has_purchase_order' => $purchaseOrders->isNotEmpty(),
                'active_commitment_amount' => (int) $commitments
                    ->where('commitment_status', ProcurementCommitment::STATUS_ACTIVE)
                    ->sum('amount'),
                'latest_match_status' => (string) ($matchResults->sortByDesc('id')->first()?->match_status ?? ''),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function paymentTrace(SpendRequest $request): array
    {
        $attempt = $request->payoutExecutionAttempt;
        $runItems = collect();

        if ($attempt instanceof RequestPayoutExecutionAttempt) {
            $runItems = PaymentRunItem::query()
                ->where('company_id', (int) $request->company_id)
                ->where('request_payout_execution_attempt_id', (int) $attempt->id)
                ->with('run:id,company_id,run_code,run_status,run_type')
                ->latest('id')
                ->get();
        }

        return [
            'attempt' => $attempt instanceof RequestPayoutExecutionAttempt ? [
                'id' => (int) $attempt->id,
                'provider' => (string) $attempt->provider_key,
                'method' => (string) $attempt->execution_channel,
                'status' => (string) $attempt->execution_status,
                'amount' => (float) $attempt->amount,
                'currency' => strtoupper((string) ($attempt->currency_code ?: 'NGN')),
                'provider_reference' => (string) ($attempt->provider_reference ?? ''),
                'external_transfer_id' => (string) ($attempt->external_transfer_id ?? ''),
                'queued_at' => $this->dateString($attempt->queued_at),
                'processed_at' => $this->dateString($attempt->processed_at),
                'settled_at' => $this->dateString($attempt->settled_at),
                'failed_at' => $this->dateString($attempt->failed_at),
                'error_code' => (string) ($attempt->error_code ?? ''),
                'error_message' => (string) ($attempt->error_message ?? ''),
            ] : null,
            'payment_run_items' => $runItems
                ->map(fn (PaymentRunItem $item): array => [
                    'id' => (int) $item->id,
                    'payment_run_id' => (int) $item->payment_run_id,
                    'run_code' => (string) ($item->run?->run_code ?? ''),
                    'run_status' => (string) ($item->run?->run_status ?? ''),
                    'status' => (string) $item->item_status,
                    'amount' => (int) $item->amount,
                    'currency' => strtoupper((string) ($item->currency_code ?: 'NGN')),
                    'provider_reference' => (string) ($item->provider_reference ?? ''),
                    'processed_at' => $this->dateString($item->processed_at),
                    'settled_at' => $this->dateString($item->settled_at),
                    'failed_at' => $this->dateString($item->failed_at),
                ])
                ->all(),
            'summary' => [
                'has_payment_attempt' => $attempt instanceof RequestPayoutExecutionAttempt,
                'status' => $attempt instanceof RequestPayoutExecutionAttempt ? (string) $attempt->execution_status : '',
                'has_payment_run_item' => $runItems->isNotEmpty(),
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function expenseTrace(SpendRequest $request): array
    {
        return $request->expenses
            ->sortByDesc('id')
            ->map(fn (Expense $expense): array => [
                'id' => (int) $expense->id,
                'expense_code' => (string) $expense->expense_code,
                'status' => (string) $expense->status,
                'amount' => (int) $expense->amount,
                'payment_method' => (string) ($expense->payment_method ?? ''),
                'expense_date' => $this->dateString($expense->expense_date),
                'is_direct' => (bool) $expense->is_direct,
                'created_at' => $this->dateString($expense->created_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int,Expense>  $expenses
     * @return array<string,mixed>
     */
    private function reconciliationTrace(int $companyId, ?RequestPayoutExecutionAttempt $payoutAttempt, Collection $expenses): array
    {
        if (! $payoutAttempt instanceof RequestPayoutExecutionAttempt && $expenses->isEmpty()) {
            return [
                'matches' => [],
                'exceptions' => [],
                'summary' => [
                    'has_match' => false,
                    'open_exceptions' => 0,
                ],
            ];
        }

        $matches = ReconciliationMatch::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($payoutAttempt, $expenses): void {
                if ($payoutAttempt instanceof RequestPayoutExecutionAttempt) {
                    $query->orWhere(function (Builder $inner) use ($payoutAttempt): void {
                        $inner->where('match_target_type', RequestPayoutExecutionAttempt::class)
                            ->where('match_target_id', (int) $payoutAttempt->id);
                    });
                }

                $expenseIds = $expenses->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($expenseIds !== []) {
                    $query->orWhere(function (Builder $inner) use ($expenseIds): void {
                        $inner->where('match_target_type', Expense::class)
                            ->whereIn('match_target_id', $expenseIds);
                    });
                }
            })
            ->with('line:id,company_id,line_reference,posted_at,description,amount,currency_code,is_reconciled')
            ->latest('matched_at')
            ->latest('id')
            ->get();

        $matchIds = $matches->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $lineIds = $matches->pluck('bank_statement_line_id')->map(fn ($id): int => (int) $id)->all();

        $exceptions = collect();
        if ($matchIds !== [] || $lineIds !== []) {
            $exceptions = ReconciliationException::query()
                ->where('company_id', $companyId)
                ->where(function (Builder $query) use ($matchIds, $lineIds): void {
                    if ($matchIds !== []) {
                        $query->orWhereIn('reconciliation_match_id', $matchIds);
                    }

                    if ($lineIds !== []) {
                        $query->orWhereIn('bank_statement_line_id', $lineIds);
                    }
                })
                ->with('line:id,company_id,line_reference,posted_at,description,amount,currency_code,is_reconciled')
                ->latest('id')
                ->get();
        }

        if ($payoutAttempt instanceof RequestPayoutExecutionAttempt) {
            $handoffExceptions = ReconciliationException::query()
                ->where('company_id', $companyId)
                ->whereIn('exception_code', ['execution_payout_failed', 'execution_payout_reversed'])
                ->latest('id')
                ->get()
                ->filter(fn (ReconciliationException $exception): bool => (int) data_get((array) ($exception->metadata ?? []), 'payout_attempt_id', 0) === (int) $payoutAttempt->id);

            $exceptions = $exceptions->merge($handoffExceptions)->unique('id')->values();
        }

        return [
            'matches' => $matches
                ->map(fn (ReconciliationMatch $match): array => [
                    'id' => (int) $match->id,
                    'target_type' => (string) $match->match_target_type,
                    'target_id' => (int) $match->match_target_id,
                    'stream' => (string) $match->match_stream,
                    'status' => (string) $match->match_status,
                    'confidence' => $match->confidence_score !== null ? (float) $match->confidence_score : null,
                    'matched_by' => (string) $match->matched_by,
                    'matched_at' => $this->dateString($match->matched_at),
                    'line_reference' => (string) ($match->line?->line_reference ?? ''),
                    'line_amount' => $match->line ? (int) $match->line->amount : null,
                    'line_is_reconciled' => $match->line ? (bool) $match->line->is_reconciled : false,
                ])
                ->all(),
            'exceptions' => $exceptions
                ->map(fn (ReconciliationException $exception): array => [
                    'id' => (int) $exception->id,
                    'code' => (string) $exception->exception_code,
                    'status' => (string) $exception->exception_status,
                    'severity' => (string) $exception->severity,
                    'stream' => (string) $exception->match_stream,
                    'next_action' => (string) ($exception->next_action ?? ''),
                    'details' => (string) ($exception->details ?? ''),
                    'line_reference' => (string) ($exception->line?->line_reference ?? ''),
                    'resolved_at' => $this->dateString($exception->resolved_at),
                    'created_at' => $this->dateString($exception->created_at),
                ])
                ->all(),
            'summary' => [
                'has_match' => $matches->isNotEmpty(),
                'open_exceptions' => (int) $exceptions
                    ->where('exception_status', ReconciliationException::STATUS_OPEN)
                    ->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int,PurchaseOrder>  $purchaseOrders
     * @param  Collection<int,Expense>  $expenses
     * @return array<string,mixed>
     */
    private function auditTrace(
        SpendRequest $request,
        ?RequestPayoutExecutionAttempt $payoutAttempt,
        Collection $purchaseOrders,
        Collection $expenses
    ): array {
        $activityLogs = ActivityLog::query()
            ->where('company_id', (int) $request->company_id)
            ->where(function (Builder $query) use ($request, $expenses): void {
                $query->where(function (Builder $inner) use ($request): void {
                    $inner->where('entity_type', SpendRequest::class)
                        ->where('entity_id', (int) $request->id);
                });

                $expenseIds = $expenses->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($expenseIds !== []) {
                    $query->orWhere(function (Builder $inner) use ($expenseIds): void {
                        $inner->where('entity_type', Expense::class)
                            ->whereIn('entity_id', $expenseIds);
                    });
                }
            })
            ->latest('created_at')
            ->limit(50)
            ->get();

        $tenantAuditEvents = TenantAuditEvent::query()
            ->where('company_id', (int) $request->company_id)
            ->where(function (Builder $query) use ($request, $payoutAttempt, $purchaseOrders, $expenses): void {
                $this->orEntityPair($query, SpendRequest::class, [(int) $request->id]);

                if ($payoutAttempt instanceof RequestPayoutExecutionAttempt) {
                    $this->orEntityPair($query, RequestPayoutExecutionAttempt::class, [(int) $payoutAttempt->id]);
                }

                $poIds = $purchaseOrders->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($poIds !== []) {
                    $this->orEntityPair($query, PurchaseOrder::class, $poIds);
                }

                $commitmentIds = $purchaseOrders
                    ->flatMap(fn (PurchaseOrder $order) => $order->commitments)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                if ($commitmentIds !== []) {
                    $this->orEntityPair($query, ProcurementCommitment::class, $commitmentIds);
                }

                $expenseIds = $expenses->pluck('id')->map(fn ($id): int => (int) $id)->all();
                if ($expenseIds !== []) {
                    $this->orEntityPair($query, Expense::class, $expenseIds);
                }
            })
            ->latest('event_at')
            ->latest('id')
            ->limit(80)
            ->get();

        return [
            'activity_logs' => $activityLogs
                ->map(fn (ActivityLog $log): array => [
                    'id' => (int) $log->id,
                    'action' => (string) $log->action,
                    'entity_type' => (string) $log->entity_type,
                    'entity_id' => $log->entity_id !== null ? (int) $log->entity_id : null,
                    'created_at' => $this->dateString($log->created_at),
                ])
                ->all(),
            'tenant_audit_events' => $tenantAuditEvents
                ->map(fn (TenantAuditEvent $event): array => [
                    'id' => (int) $event->id,
                    'action' => (string) $event->action,
                    'entity_type' => (string) ($event->entity_type ?? ''),
                    'entity_id' => $event->entity_id !== null ? (int) $event->entity_id : null,
                    'description' => (string) ($event->description ?? ''),
                    'event_at' => $this->dateString($event->event_at),
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<int,int>  $ids
     */
    private function orEntityPair(Builder $query, string $entityType, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $query->orWhere(function (Builder $inner) use ($entityType, $ids): void {
            $inner->where('entity_type', $entityType)
                ->whereIn('entity_id', $ids);
        });
    }

    /**
     * @param  array<string,mixed>  $budget
     * @param  array<int,array<string,mixed>>  $approvals
     * @param  array<string,mixed>  $procurement
     * @param  array<string,mixed>  $payment
     * @param  array<int,array<string,mixed>>  $expenses
     * @param  array<string,mixed>  $reconciliation
     * @param  array<string,mixed>  $audit
     * @return array<int,array<string,mixed>>
     */
    private function timeline(
        SpendRequest $request,
        array $budget,
        array $approvals,
        array $procurement,
        array $payment,
        array $expenses,
        array $reconciliation,
        array $audit
    ): array {
        $rows = [
            $this->timelineRow('budget', 'Budget checked', (string) $budget['status'], $request->submitted_at ?? $request->created_at, (int) ($budget['projected_amount'] ?? 0)),
            $this->timelineRow('request', 'Request created', (string) $request->status, $request->created_at, (int) $request->amount),
        ];

        foreach ($approvals as $approval) {
            $rows[] = $this->timelineRow('approval', 'Approval '.$approval['step_order'], (string) $approval['status'], $approval['acted_at'] ?: $approval['due_at'], null);
        }

        foreach ($procurement['purchase_orders'] as $order) {
            $rows[] = $this->timelineRow('procurement', 'Purchase order '.$order['po_number'], (string) $order['status'], $order['issued_at'] ?: $order['created_at'], (int) $order['amount']);
        }

        foreach ($procurement['commitments'] as $commitment) {
            $rows[] = $this->timelineRow('budget_commitment', 'Budget commitment', (string) $commitment['status'], $commitment['effective_at'], (int) $commitment['amount']);
        }

        $attempt = $payment['attempt'] ?? null;
        if (is_array($attempt)) {
            $rows[] = $this->timelineRow(
                'payment',
                'Payment attempt',
                (string) $attempt['status'],
                $attempt['settled_at'] ?: ($attempt['failed_at'] ?: ($attempt['processed_at'] ?: $attempt['queued_at'])),
                (int) $attempt['amount']
            );
        }

        foreach ($expenses as $expense) {
            $rows[] = $this->timelineRow('expense', 'Expense '.$expense['expense_code'], (string) $expense['status'], $expense['created_at'] ?: $expense['expense_date'], (int) $expense['amount']);
        }

        foreach ($reconciliation['matches'] as $match) {
            $rows[] = $this->timelineRow('reconciliation', 'Bank match '.$match['line_reference'], (string) $match['status'], $match['matched_at'], $match['line_amount']);
        }

        foreach ($reconciliation['exceptions'] as $exception) {
            $rows[] = $this->timelineRow('reconciliation_exception', 'Bank item '.$exception['code'], (string) $exception['status'], $exception['resolved_at'] ?: $exception['created_at'], null);
        }

        foreach ($audit['activity_logs'] as $log) {
            $rows[] = $this->timelineRow('audit', (string) $log['action'], 'logged', $log['created_at'], null);
        }

        foreach ($audit['tenant_audit_events'] as $event) {
            $rows[] = $this->timelineRow('audit', (string) $event['action'], 'logged', $event['event_at'], null);
        }

        return collect($rows)
            ->filter(fn (array $row): bool => $row['occurred_at'] !== null)
            ->sortBy('occurred_at')
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function timelineRow(string $stage, string $label, string $status, mixed $occurredAt, ?int $amount): array
    {
        return [
            'stage' => $stage,
            'label' => trim($label),
            'status' => $status,
            'occurred_at' => $this->dateString($occurredAt),
            'amount' => $amount,
        ];
    }

    /**
     * @param  array<string,mixed>  $budget
     * @param  array<string,mixed>  $procurement
     * @param  array<string,mixed>  $payment
     * @param  array<int,array<string,mixed>>  $expenses
     * @param  array<string,mixed>  $reconciliation
     * @return array<int,array{key:string,label:string,severity:string}>
     */
    private function gaps(array $budget, array $procurement, array $payment, array $expenses, array $reconciliation): array
    {
        $gaps = [];

        if (! (bool) ($budget['has_budget'] ?? false)) {
            $gaps[] = ['key' => 'budget_missing', 'label' => 'No budget was found for this request period.', 'severity' => 'medium'];
        }

        if (($payment['summary']['status'] ?? '') === 'settled' && $expenses === []) {
            $gaps[] = ['key' => 'settled_without_expense', 'label' => 'Payment is settled, but no linked expense is recorded yet.', 'severity' => 'high'];
        }

        if (($payment['summary']['status'] ?? '') === 'settled' && ! (bool) ($reconciliation['summary']['has_match'] ?? false)) {
            $gaps[] = ['key' => 'settled_without_reconciliation', 'label' => 'Payment is settled, but no bank reconciliation match is linked yet.', 'severity' => 'medium'];
        }

        if (($procurement['summary']['has_purchase_order'] ?? false) && (($procurement['summary']['active_commitment_amount'] ?? 0) < 1)) {
            $gaps[] = ['key' => 'po_without_commitment', 'label' => 'Purchase order exists, but no active budget commitment is linked.', 'severity' => 'medium'];
        }

        return $gaps;
    }

    /**
     * @param  array<string,mixed>  $budget
     * @param  array<int,array<string,mixed>>  $approvals
     * @param  array<string,mixed>  $procurement
     * @param  array<string,mixed>  $payment
     * @param  array<int,array<string,mixed>>  $expenses
     * @param  array<string,mixed>  $reconciliation
     * @param  array<int,array{key:string,label:string,severity:string}>  $gaps
     * @return array{key:string,label:string,severity:string}
     */
    private function completionStatus(
        SpendRequest $request,
        array $budget,
        array $approvals,
        array $procurement,
        array $payment,
        array $expenses,
        array $reconciliation,
        array $gaps
    ): array {
        $paymentStatus = (string) data_get($payment, 'summary.status', '');
        $requestStatus = (string) $request->status;
        $gapKeys = array_values(array_map(static fn (array $gap): string => (string) ($gap['key'] ?? ''), $gaps));

        if ($paymentStatus === 'failed' || $requestStatus === 'failed') {
            return $this->completionRow('payment_failed', 'Payment Failed', 'high');
        }

        if ($paymentStatus === 'reversed' || $requestStatus === 'reversed') {
            return $this->completionRow('payment_reversed', 'Payment Reversed', 'high');
        }

        if (! (bool) ($budget['has_budget'] ?? false)) {
            return $this->completionRow('needs_budget', 'Needs Budget', 'medium');
        }

        if (in_array('settled_without_expense', $gapKeys, true)) {
            return $this->completionRow('needs_expense', 'Needs Expense', 'high');
        }

        if (in_array('settled_without_reconciliation', $gapKeys, true)) {
            return $this->completionRow('needs_bank_match', 'Needs Bank Match', 'medium');
        }

        if (in_array('po_without_commitment', $gapKeys, true)) {
            return $this->completionRow('needs_po_commitment', 'Needs PO Commitment', 'medium');
        }

        $hasPendingApproval = collect($approvals)->contains(
            static fn (array $approval): bool => in_array((string) ($approval['status'] ?? ''), ['pending', 'queued'], true)
        );
        if ($hasPendingApproval || $requestStatus === 'in_review') {
            return $this->completionRow('needs_approval', 'Needs Approval', 'medium');
        }

        if (in_array($paymentStatus, ['queued', 'processing', 'webhook_pending'], true) || in_array($requestStatus, ['execution_queued', 'execution_processing'], true)) {
            return $this->completionRow('payment_in_progress', 'Payment In Progress', 'low');
        }

        if (in_array($requestStatus, ['approved', 'approved_for_execution'], true) && ! (bool) data_get($payment, 'summary.has_payment_attempt', false)) {
            return $this->completionRow('needs_payment', 'Needs Payment', 'medium');
        }

        if ($paymentStatus === 'settled' && $expenses !== [] && (bool) data_get($reconciliation, 'summary.has_match', false) && $gaps === []) {
            return $this->completionRow('complete', 'Complete', 'low');
        }

        return $this->completionRow('in_progress', 'In Progress', 'low');
    }

    /**
     * @return array{key:string,label:string,severity:string}
     */
    private function completionRow(string $key, string $label, string $severity): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
        ];
    }

    private function referenceBudgetDate(SpendRequest $request): string
    {
        return $request->submitted_at?->toDateString()
            ?: $request->created_at?->toDateString()
            ?: now()->toDateString();
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
