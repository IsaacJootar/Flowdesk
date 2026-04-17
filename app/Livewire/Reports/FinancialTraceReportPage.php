<?php

namespace App\Livewire\Reports;

use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Services\FinancialTraceService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Budget to Payment Trace')]
class FinancialTraceReportPage extends Component
{
    use WithPagination;

    private const ALLOWED_PER_PAGE = [10, 25, 50];

    private const ALLOWED_STATUS_FILTERS = [
        'all',
        'draft',
        'in_review',
        'approved',
        'approved_for_execution',
        'execution_queued',
        'execution_processing',
        'settled',
        'failed',
        'reversed',
        'rejected',
        'returned',
    ];

    private const ALLOWED_PAYMENT_FILTERS = [
        'all',
        'no_payment',
        'queued',
        'processing',
        'settled',
        'failed',
        'reversed',
    ];

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $paymentFilter = 'all';

    public string $departmentFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 10;

    public function mount(): void
    {
        abort_unless($this->canAccessReport(), 403);
        $this->normalizeFilterState();
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->resetPage();
    }

    public function updatedPaymentFilter(): void
    {
        $this->paymentFilter = $this->normalizePaymentFilter($this->paymentFilter);
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->departmentFilter = $this->normalizeDepartmentFilter($this->departmentFilter);
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->dateFrom = $this->normalizeDate($this->dateFrom);
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->dateTo = $this->normalizeDate($this->dateTo);
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);
        $this->resetPage();
    }

    public function render(FinancialTraceService $financialTraceService): View
    {
        $this->normalizeFilterState();

        $departments = Department::query()
            ->where('company_id', (int) Auth::user()?->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $baseQuery = $this->baseQuery();
        $metrics = $this->readyToLoad ? $this->buildMetrics(clone $baseQuery) : $this->emptyMetrics();

        $requests = $this->readyToLoad
            ? (clone $baseQuery)
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
                ->latest('updated_at')
                ->latest('id')
                ->paginate($this->perPage)
            : SpendRequest::query()->whereRaw('1 = 0')->paginate($this->perPage);

        $traceRows = $this->readyToLoad
            ? $this->traceRows($requests->getCollection(), $financialTraceService)
            : [];

        if ($this->readyToLoad) {
            $metrics['trace_notes_on_page'] = array_sum(array_map(
                static fn (array $row): int => count($row['gaps']),
                $traceRows
            ));
        }

        return view('livewire.reports.financial-trace-report-page', [
            'departments' => $departments,
            'requests' => $requests,
            'traceRows' => $traceRows,
            'metrics' => $metrics,
            'statusOptions' => array_values(array_filter(self::ALLOWED_STATUS_FILTERS, static fn (string $status): bool => $status !== 'all')),
            'paymentOptions' => array_values(array_filter(self::ALLOWED_PAYMENT_FILTERS, static fn (string $status): bool => $status !== 'all')),
            'currencyCode' => strtoupper((string) (Auth::user()?->company?->currency_code ?: 'NGN')),
        ]);
    }

    private function baseQuery(): Builder
    {
        $query = SpendRequest::query();
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('company_id', (int) $user->company_id);

        if ($this->search !== '') {
            $search = $this->normalizeSearch($this->search);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('requester', fn (Builder $requester) => $requester->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->paymentFilter === 'no_payment') {
            $query->whereDoesntHave('payoutExecutionAttempt');
        } elseif ($this->paymentFilter !== 'all') {
            $query->whereHas(
                'payoutExecutionAttempt',
                fn (Builder $attempt): Builder => $attempt->where('execution_status', $this->paymentFilter)
            );
        }

        if ($this->departmentFilter !== 'all') {
            $query->where('department_id', (int) $this->departmentFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $this->applyRoleScope($query);
    }

    private function applyRoleScope(Builder $query): Builder
    {
        $user = Auth::user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array((string) $user->role, [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value], true)) {
            return $query;
        }

        if ((string) $user->role === UserRole::Manager->value) {
            return $query->where(function (Builder $builder) use ($user): void {
                if ($user->department_id) {
                    $builder->where('department_id', (int) $user->department_id)
                        ->orWhere('requested_by', (int) $user->id);
                } else {
                    $builder->where('requested_by', (int) $user->id);
                }
            });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array{total_requests:int,total_amount:int,payment_attempts:int,settled_payments:int,purchase_orders:int,linked_expenses:int,trace_notes_on_page:int}
     */
    private function buildMetrics(Builder $query): array
    {
        return [
            'total_requests' => (int) (clone $query)->count(),
            'total_amount' => (int) (clone $query)->sum('amount'),
            'payment_attempts' => (int) (clone $query)->has('payoutExecutionAttempt')->count(),
            'settled_payments' => (int) (clone $query)->whereHas(
                'payoutExecutionAttempt',
                fn (Builder $attempt): Builder => $attempt->where('execution_status', 'settled')
            )->count(),
            'purchase_orders' => (int) (clone $query)->has('purchaseOrders')->count(),
            'linked_expenses' => (int) (clone $query)->has('expenses')->count(),
            'trace_notes_on_page' => 0,
        ];
    }

    /**
     * @return array{total_requests:int,total_amount:int,payment_attempts:int,settled_payments:int,purchase_orders:int,linked_expenses:int,trace_notes_on_page:int}
     */
    private function emptyMetrics(): array
    {
        return [
            'total_requests' => 0,
            'total_amount' => 0,
            'payment_attempts' => 0,
            'settled_payments' => 0,
            'purchase_orders' => 0,
            'linked_expenses' => 0,
            'trace_notes_on_page' => 0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,SpendRequest>  $requests
     * @return array<int,array<string,mixed>>
     */
    private function traceRows(\Illuminate\Support\Collection $requests, FinancialTraceService $financialTraceService): array
    {
        return $requests
            ->map(fn (SpendRequest $request): array => $this->traceRow($request, $financialTraceService->buildForRequest($request)))
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $trace
     * @return array<string,mixed>
     */
    private function traceRow(SpendRequest $request, array $trace): array
    {
        $approvalCount = count((array) ($trace['approvals'] ?? []));
        $approvedSteps = count(array_filter(
            (array) ($trace['approvals'] ?? []),
            static fn (mixed $approval): bool => is_array($approval) && (string) ($approval['status'] ?? '') === 'approved'
        ));
        $purchaseOrders = (array) data_get($trace, 'procurement.purchase_orders', []);
        $commitmentAmount = (int) data_get($trace, 'procurement.summary.active_commitment_amount', 0);
        $expenses = (array) ($trace['expenses'] ?? []);
        $expenseAmount = array_sum(array_map(
            static fn (mixed $expense): int => is_array($expense) ? (int) ($expense['amount'] ?? 0) : 0,
            $expenses
        ));
        $paymentAttempt = (array) data_get($trace, 'payment.attempt', []);
        $paymentStatus = (string) data_get($trace, 'payment.summary.status', '');
        $hasPaymentAttempt = (bool) data_get($trace, 'payment.summary.has_payment_attempt', false);
        $openExceptions = (int) data_get($trace, 'reconciliation.summary.open_exceptions', 0);
        $hasBankMatch = (bool) data_get($trace, 'reconciliation.summary.has_match', false);
        $auditCount = count((array) data_get($trace, 'audit.activity_logs', [])) + count((array) data_get($trace, 'audit.tenant_audit_events', []));

        return [
            'id' => (int) $request->id,
            'request_code' => (string) $request->request_code,
            'title' => (string) $request->title,
            'requester' => (string) ($request->requester?->name ?? '-'),
            'department' => (string) ($request->department?->name ?? '-'),
            'vendor' => (string) ($request->vendor?->name ?? 'Unlinked'),
            'amount' => (int) $request->amount,
            'currency' => strtoupper((string) $request->currency),
            'request_status' => (string) $request->status,
            'budget_status' => $this->label((string) data_get($trace, 'budget.status', 'no_budget_found')),
            'approval_status' => $approvalCount > 0 ? $approvedSteps.' / '.$approvalCount.' approved' : 'No approval rows',
            'purchase_order_status' => count($purchaseOrders) > 0
                ? count($purchaseOrders).' order(s), '.\App\Support\Money::formatCurrency($commitmentAmount, strtoupper((string) $request->currency)).' committed'
                : 'No purchase order',
            'payment_status' => $hasPaymentAttempt ? $this->label($paymentStatus) : 'Not queued',
            'payment_method' => $this->label((string) ($paymentAttempt['method'] ?? '')),
            'payment_reference' => (string) ($paymentAttempt['provider_reference'] ?? ''),
            'expense_status' => count($expenses) > 0
                ? count($expenses).' record(s), '.\App\Support\Money::formatCurrency((int) $expenseAmount, strtoupper((string) $request->currency)).' posted'
                : 'No expense record',
            'reconciliation_status' => $hasBankMatch
                ? ($openExceptions > 0 ? 'Matched with exceptions' : 'Matched')
                : 'Not matched',
            'audit_status' => $auditCount.' event(s)',
            'gaps' => collect((array) ($trace['gaps'] ?? []))
                ->map(fn (mixed $gap): array => [
                    'label' => is_array($gap) ? (string) ($gap['label'] ?? '') : '',
                    'severity' => is_array($gap) ? (string) ($gap['severity'] ?? 'medium') : 'medium',
                ])
                ->filter(fn (array $gap): bool => $gap['label'] !== '')
                ->values()
                ->all(),
            'url' => route('requests.index', ['open_request_id' => (int) $request->id]),
        ];
    }

    private function normalizeFilterState(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->paymentFilter = $this->normalizePaymentFilter($this->paymentFilter);
        $this->departmentFilter = $this->normalizeDepartmentFilter($this->departmentFilter);
        $this->dateFrom = $this->normalizeDate($this->dateFrom);
        $this->dateTo = $this->normalizeDate($this->dateTo);
        $this->normalizeDateRange();
        $this->perPage = $this->normalizePerPage($this->perPage);
    }

    private function normalizeSearch(string $value): string
    {
        return mb_substr(trim($value), 0, 120);
    }

    private function normalizeStatusFilter(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, self::ALLOWED_STATUS_FILTERS, true) ? $normalized : 'all';
    }

    private function normalizePaymentFilter(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, self::ALLOWED_PAYMENT_FILTERS, true) ? $normalized : 'all';
    }

    private function normalizeDepartmentFilter(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strtolower($normalized) === 'all' || ! ctype_digit($normalized)) {
            return 'all';
        }

        return ((int) $normalized) > 0 ? (string) ((int) $normalized) : 'all';
    }

    private function normalizeDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasWarnings = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if (! $parsed instanceof \DateTimeImmutable || $hasWarnings) {
            return '';
        }

        return $parsed->format('Y-m-d');
    }

    private function normalizeDateRange(): void
    {
        if ($this->dateFrom !== '' && $this->dateTo !== '' && $this->dateFrom > $this->dateTo) {
            $this->dateTo = '';
        }
    }

    private function normalizePerPage(int $value): int
    {
        return in_array($value, self::ALLOWED_PER_PAGE, true) ? $value : 10;
    }

    private function canAccessReport(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function label(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }
}
