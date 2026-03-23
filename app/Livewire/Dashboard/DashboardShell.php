<?php

namespace App\Livewire\Dashboard;

use App\Domains\Assets\Models\Asset;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Procurement\ProcurementControlSettingsService;
use App\Services\TenantModuleAccessService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class DashboardShell extends Component
{
    public bool $readyToLoad = false;

    /** @var array<string, array{label: string, value: string, hint: string, words?: string}> */
    public array $metrics = [];

    public string $roleView = 'general';

    public string $roleTitle = 'Operations Snapshot';

    public string $roleDescription = 'Quick summary of spend, requests, and controls for your tenant.';

    /** @var array<int, array{label:string,value:string,hint:string,tone:string}> */
    public array $roleSummaryCards = [];

    /** @var array<int, array{label:string,route:string,url:string,hint:string}> */
    public array $priorityActions = [];

    /** @var array<int, array{label:string,time:string,detail:string}> */
    public array $recentSignals = [];

    public function mount(): void
    {
        // Keep initial render light; metrics are loaded via wire:init.
        $this->metrics = $this->defaultMetrics();
        $this->applyRoleContext(Auth::user());
    }

    public function loadMetrics(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;

        $user = Auth::user();
        $this->applyRoleContext($user);

        $companyId = (int) ($user?->company_id ?? 0);
        if ($companyId <= 0 || ! $user) {
            $this->metrics = $this->defaultMetrics();
            $this->roleSummaryCards = [];
            $this->priorityActions = [];
            $this->recentSignals = [];

            return;
        }

        $currencyCode = strtoupper((string) ($user?->company?->currency_code ?: 'NGN'));
        $snapshot = $this->resolveDashboardSnapshot($user, $companyId, $currencyCode);
        $this->metrics = (array) ($snapshot['metrics'] ?? $this->defaultMetrics());
        $this->roleSummaryCards = (array) ($snapshot['roleSummaryCards'] ?? []);
        $this->priorityActions = (array) ($snapshot['priorityActions'] ?? []);
        $this->recentSignals = (array) ($snapshot['recentSignals'] ?? []);
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-shell');
    }

    /**
     * @return array<string, array{label: string, value: string, hint: string, words?: string}>
     */
    private function defaultMetrics(): array
    {
        return [
            'total_spend' => [
                'label' => 'Total Spend (This Month)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'pending_approvals' => [
                'label' => 'Requests In Review',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'approved_value_month' => [
                'label' => 'Approved Value (This Month)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'approved_budget' => [
                'label' => 'Approved Budget (Active)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'budget_remaining' => [
                'label' => 'Budget Remaining (Active)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'assets_overview' => [
                'label' => 'Assets Overview',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
        ];
    }

    private function applyRoleContext(?User $user): void
    {
        $role = strtolower((string) ($user?->role ?? ''));

        if ($role === UserRole::Finance->value) {
            $this->roleView = 'finance';
            $this->roleTitle = 'Finance Command Center';
            $this->roleDescription = 'Queue health, blocked handoffs, and reconciliation workload for finance operations.';

            return;
        }

        if ($role === UserRole::Owner->value) {
            $this->roleView = 'owner';
            $this->roleTitle = 'Owner Control Tower';
            $this->roleDescription = 'Cross-lane control posture and policy-risk signals across your organization.';

            return;
        }

        if ($role === UserRole::Auditor->value) {
            $this->roleView = 'auditor';
            $this->roleTitle = 'Audit & Assurance Lens';
            $this->roleDescription = 'Traceability and override-risk posture for independent reviews.';

            return;
        }

        $this->roleView = 'general';
        $this->roleTitle = 'Operations Snapshot';
        $this->roleDescription = 'Quick summary of spend, requests, and controls for your tenant.';
    }

    private function buildRoleLens(User $user, int $companyId, string $currencyCode): void
    {
        $staleCommitments = $this->staleCommitmentCount($companyId);
        $procurementOpen = (int) InvoiceMatchException::query()
            ->where('company_id', $companyId)
            ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
            ->count();
        $procurementCritical = (int) InvoiceMatchException::query()
            ->where('company_id', $companyId)
            ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
            ->whereIn('severity', [InvoiceMatchException::SEVERITY_HIGH, InvoiceMatchException::SEVERITY_CRITICAL])
            ->count();

        $treasuryOpen = (int) ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->count();
        $treasuryCritical = (int) ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('exception_status', ReconciliationException::STATUS_OPEN)
            ->whereIn('severity', [ReconciliationException::SEVERITY_HIGH, ReconciliationException::SEVERITY_CRITICAL])
            ->count();

        $queueRisk = $this->oldQueuedExecutionCount($companyId);
        $executionAlerts24h = $this->countAuditActions(
            $companyId,
            ['tenant.execution.alert.summary_emitted'],
            Carbon::now()->subDay()
        );

        $blockedPayout30d = $this->countAuditActions(
            $companyId,
            ['tenant.execution.payout.blocked_by_procurement_match'],
            Carbon::now()->subDays(30)
        );

        $this->roleSummaryCards = [];
        $this->priorityActions = [];

        if ($this->roleView === 'finance') {
            $this->roleSummaryCards = [
                [
                    'label' => 'Open Procurement Exceptions',
                    'value' => $this->formatCount($procurementOpen),
                    'hint' => $this->formatCount($procurementCritical).' high/critical',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Open Treasury Exceptions',
                    'value' => $this->formatCount($treasuryOpen),
                    'hint' => $this->formatCount($treasuryCritical).' high/critical',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Stale Execution Queue',
                    'value' => $this->formatCount($queueRisk),
                    'hint' => 'Queued records above recovery age',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Blocked Payout Handoffs (30d)',
                    'value' => $this->formatCount($blockedPayout30d),
                    'hint' => 'Procurement gate prevented payout queueing',
                    'tone' => 'slate',
                ],
            ];

            $this->pushPriorityAction($user, 'execution.payout-ready', 'Run payout-ready queue', 'Process approved requests waiting for payout execution.');
            $this->pushPriorityAction($user, 'execution.health', 'Review execution health', 'Track current incidents and recent recovery outcomes.');
            $this->pushPriorityAction($user, 'procurement.release-desk', 'Clear procurement exceptions', 'Resolve 3-way match blockers before payout handoff.');
            $this->pushPriorityAction($user, 'treasury.reconciliation-exceptions', 'Work treasury exceptions', 'Close open reconciliation backlog items.');
            $this->pushPriorityAction($user, 'reports.index', 'Open reports center', 'Review reconciled vs unreconciled trends.');

            $this->recentSignals = $this->recentSignalsForActions($companyId, [
                'tenant.execution.alert.summary_emitted',
                'tenant.execution.payout.blocked_by_procurement_match',
                'tenant.procurement.match.failed',
                'tenant.execution.auto_recovery.run_summary',
                'tenant.treasury.reconciliation.auto_run',
            ]);

            return;
        }

        if ($this->roleView === 'owner') {
            $controlDenials7d = $this->countAuditActions(
                $companyId,
                [
                    'tenant.procurement.match.exception.action.denied',
                    'tenant.treasury.exception.action.denied',
                ],
                Carbon::now()->subDays(7)
            );

            $this->roleSummaryCards = [
                [
                    'label' => 'Control Breach Signals (7d)',
                    'value' => $this->formatCount($controlDenials7d + $blockedPayout30d),
                    'hint' => $this->formatCount($controlDenials7d).' denied actions + '.$this->formatCount($blockedPayout30d).' blocked payout handoffs',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Stale Commitments',
                    'value' => $this->formatCount($staleCommitments),
                    'hint' => 'Active procurement commitments above tenant age threshold',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Execution Alerts (24h)',
                    'value' => $this->formatCount($executionAlerts24h),
                    'hint' => 'Alerts emitted by execution ops summary runs',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Open Finance Exceptions',
                    'value' => $this->formatCount($procurementOpen + $treasuryOpen),
                    'hint' => $this->formatCount($procurementOpen).' procurement + '.$this->formatCount($treasuryOpen).' treasury',
                    'tone' => 'slate',
                ],
            ];

            $this->pushPriorityAction($user, 'settings.procurement-controls', 'Tune procurement controls', 'Update mandatory PO, match, and stale commitment thresholds.');
            $this->pushPriorityAction($user, 'settings.treasury-controls', 'Tune treasury controls', 'Adjust backlog alert and reconciliation guardrails.');
            $this->pushPriorityAction($user, 'execution.payout-ready', 'Run payout-ready queue', 'Process approved requests waiting for payout execution.');
            $this->pushPriorityAction($user, 'execution.health', 'Review execution health', 'Validate tenant-facing execution status and incidents.');
            $this->pushPriorityAction($user, 'reports.index', 'Review governance reports', 'Track trend lines for controls and exceptions.');

            $this->recentSignals = $this->recentSignalsForActions($companyId, [
                'tenant.procurement.controls.updated',
                'tenant.treasury.controls.updated',
                'tenant.procurement.match.exception.action.denied',
                'tenant.treasury.exception.action.denied',
                'tenant.execution.alert.summary_emitted',
                'tenant.execution.payout.blocked_by_procurement_match',
            ]);

            return;
        }

        if ($this->roleView === 'auditor') {
            $manualOverrides7d = $this->countAuditActions(
                $companyId,
                [
                    'tenant.procurement.match.exception.resolved',
                    'tenant.procurement.match.exception.waived',
                    'tenant.treasury.exception.resolved',
                    'tenant.treasury.exception.waived',
                    'tenant.execution.billing.process_stuck_queued',
                    'tenant.execution.payout.process_stuck_queued',
                ],
                Carbon::now()->subDays(7)
            );

            $deniedSensitive7d = $this->countAuditActions(
                $companyId,
                [
                    'tenant.procurement.match.exception.action.denied',
                    'tenant.treasury.exception.action.denied',
                ],
                Carbon::now()->subDays(7)
            );

            $alerts7d = $this->countAuditActions(
                $companyId,
                ['tenant.execution.alert.summary_emitted'],
                Carbon::now()->subDays(7)
            );

            $this->roleSummaryCards = [
                [
                    'label' => 'Manual Override Actions (7d)',
                    'value' => $this->formatCount($manualOverrides7d),
                    'hint' => 'Resolved/waived exceptions and manual queue recoveries',
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Denied Sensitive Actions (7d)',
                    'value' => $this->formatCount($deniedSensitive7d),
                    'hint' => 'Role or maker-checker denials',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Execution Alerts (7d)',
                    'value' => $this->formatCount($alerts7d),
                    'hint' => 'Tenant alert summaries emitted',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Open Exceptions Snapshot',
                    'value' => $this->formatCount($procurementOpen + $treasuryOpen),
                    'hint' => $this->formatCount($procurementOpen).' procurement + '.$this->formatCount($treasuryOpen).' treasury',
                    'tone' => 'slate',
                ],
            ];

            $this->pushPriorityAction($user, 'requests.communications', 'Inspect communication logs', 'Trace request and reminder delivery events.');
            $this->pushPriorityAction($user, 'procurement.release-desk', 'Inspect procurement exception queue', 'Review resolution notes and actor trail.');
            $this->pushPriorityAction($user, 'treasury.reconciliation-exceptions', 'Inspect treasury exception queue', 'Review waive/resolve decisions and maker-checker evidence.');
            $this->pushPriorityAction($user, 'reports.index', 'Export audit reports', 'Use reports center for reconciled/unreconciled and control signals.');

            $this->recentSignals = $this->recentSignalsForActions($companyId, [
                'tenant.procurement.match.exception.resolved',
                'tenant.procurement.match.exception.waived',
                'tenant.treasury.exception.resolved',
                'tenant.treasury.exception.waived',
                'tenant.procurement.match.exception.action.denied',
                'tenant.treasury.exception.action.denied',
                'tenant.execution.alert.summary_emitted',
            ]);

            return;
        }

        // Keep manager/staff dashboard simple and operational.
        $postedExpenseCount = (int) Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        $this->roleSummaryCards = [
            [
                'label' => 'This Month Posted Expenses',
                'value' => $this->formatCount($postedExpenseCount),
                'hint' => 'Posted direct and request-linked expenses',
                'tone' => 'emerald',
            ],
            [
                    'label' => 'In-Review Request Value',
                    'value' => $this->formatMoney(
                        (int) SpendRequest::query()->where('company_id', $companyId)->where('status', 'in_review')->sum('amount'),
                        $currencyCode
                    ),
                    'hint' => 'Requests waiting for approval decisions',
                    'tone' => 'sky',
                ],
        ];

        $this->pushPriorityAction($user, 'requests.index', 'Open requests', 'Create and track spend requests.');
        $this->pushPriorityAction($user, 'expenses.index', 'Open expenses', 'Post direct expenses and attach evidence.');

        $this->recentSignals = $this->recentSignalsForActions($companyId, [
            'tenant.execution.alert.summary_emitted',
            'tenant.execution.auto_recovery.run_summary',
        ]);
    }

    private function staleCommitmentCount(int $companyId): int
    {
        $controls = app(ProcurementControlSettingsService::class)->effectiveControls($companyId);
        $ageHours = max(1, (int) ($controls['stale_commitment_alert_age_hours'] ?? 72));
        $cutoff = Carbon::now()->subHours($ageHours);

        return (int) ProcurementCommitment::query()
            ->where('company_id', $companyId)
            ->where('commitment_status', ProcurementCommitment::STATUS_ACTIVE)
            ->where('effective_at', '<=', $cutoff)
            ->count();
    }

    private function oldQueuedExecutionCount(int $companyId): int
    {
        $olderThanMinutes = max(1, (int) config('execution.ops_recovery.older_than_minutes', 30));
        $cutoff = Carbon::now()->subMinutes($olderThanMinutes);

        $billing = (int) TenantSubscriptionBillingAttempt::query()
            ->where('company_id', $companyId)
            ->where('attempt_status', 'queued')
            ->whereNotNull('queued_at')
            ->where('queued_at', '<=', $cutoff)
            ->count();

        $payout = (int) RequestPayoutExecutionAttempt::query()
            ->where('company_id', $companyId)
            ->where('execution_status', 'queued')
            ->whereNotNull('queued_at')
            ->where('queued_at', '<=', $cutoff)
            ->count();

        return $billing + $payout;
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function countAuditActions(int $companyId, array $actions, Carbon $since): int
    {
        return (int) TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('action', $actions)
            ->where('event_at', '>=', $since)
            ->count();
    }

    private function pushPriorityAction(User $user, string $route, string $label, string $hint): void
    {
        if (! app(TenantModuleAccessService::class)->routeEnabled($user, $route)) {
            return;
        }

        try {
            $url = route($route);
        } catch (\Throwable) {
            return;
        }

        $this->priorityActions[] = [
            'label' => $label,
            'route' => $route,
            'url' => $url,
            'hint' => $hint,
        ];
    }

    /**
     * @param  array<int, string>  $actions
     * @return array<int, array{label:string,time:string,detail:string}>
     */
    private function recentSignalsForActions(int $companyId, array $actions): array
    {
        $events = TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('action', $actions)
            ->latest('event_at')
            ->latest('id')
            ->limit(8)
            ->get(['action', 'description', 'metadata', 'event_at']);

        return $events->map(function (TenantAuditEvent $event): array {
            $metadata = (array) ($event->metadata ?? []);

            return [
                'label' => $this->humanizeAction((string) $event->action),
                'time' => $event->event_at?->format('M d, H:i') ?? '-',
                'detail' => $this->signalDetail((string) $event->action, $metadata, (string) ($event->description ?? '')),
            ];
        })->all();
    }

    private function humanizeAction(string $action): string
    {
        return match ($action) {
            'tenant.execution.alert.summary_emitted' => 'Execution alert summary',
            'tenant.execution.auto_recovery.run_summary' => 'Auto recovery summary',
            'tenant.execution.payout.blocked_by_procurement_match' => 'Payout blocked by procurement gate',
            'tenant.procurement.match.failed' => 'Procurement match failed',
            'tenant.procurement.match.exception.resolved' => 'Procurement exception resolved',
            'tenant.procurement.match.exception.waived' => 'Procurement exception waived',
            'tenant.procurement.match.exception.action.denied' => 'Procurement exception action denied',
            'tenant.treasury.exception.resolved' => 'Treasury exception resolved',
            'tenant.treasury.exception.waived' => 'Treasury exception waived',
            'tenant.treasury.exception.action.denied' => 'Treasury exception action denied',
            'tenant.procurement.controls.updated' => 'Procurement controls updated',
            'tenant.treasury.controls.updated' => 'Treasury controls updated',
            'tenant.treasury.reconciliation.auto_run' => 'Treasury auto reconciliation run',
            'tenant.execution.billing.process_stuck_queued' => 'Billing manual recovery',
            'tenant.execution.payout.process_stuck_queued' => 'Payout manual recovery',
            default => str_replace('_', ' ', trim(str_replace('.', ' / ', $action))),
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function signalDetail(string $action, array $metadata, string $description): string
    {
        if ($action === 'tenant.execution.alert.summary_emitted') {
            $type = (string) ($metadata['type'] ?? 'alert');
            $pipeline = (string) ($metadata['pipeline'] ?? 'system');
            $count = (int) ($metadata['count'] ?? 0);

            return sprintf('%s / %s / count %d', str_replace('_', ' ', $type), $pipeline, $count);
        }

        if ($action === 'tenant.execution.auto_recovery.run_summary') {
            $pipeline = (string) ($metadata['pipeline'] ?? 'execution');
            $processed = (int) ($metadata['processed'] ?? 0);
            $matched = (int) ($metadata['matched'] ?? 0);

            return sprintf('%s processed %d of %d', ucfirst($pipeline), $processed, $matched);
        }

        if ($action === 'tenant.execution.payout.blocked_by_procurement_match') {
            return (string) ($metadata['reason'] ?? $description ?: 'Blocked by procurement policy controls.');
        }

        return $description !== '' ? $description : 'No extra detail.';
    }

    private function formatAmountInWords(int $amount, string $currencyCode): string
    {
        $unit = strtoupper($currencyCode) === 'NGN' ? 'naira' : strtolower($currencyCode);
        $words = $this->numberToWords(max(0, $amount));

        return sprintf('In words: %s %s', $words, $unit);
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $units = [
            0 => '',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
        ];

        $tens = [
            2 => 'twenty',
            3 => 'thirty',
            4 => 'forty',
            5 => 'fifty',
            6 => 'sixty',
            7 => 'seventy',
            8 => 'eighty',
            9 => 'ninety',
        ];

        $scales = [
            1_000_000_000_000 => 'trillion',
            1_000_000_000 => 'billion',
            1_000_000 => 'million',
            1_000 => 'thousand',
            1 => '',
        ];

        $parts = [];

        foreach ($scales as $scaleValue => $scaleLabel) {
            if ($number < $scaleValue) {
                continue;
            }

            $chunk = intdiv($number, $scaleValue);
            $number %= $scaleValue;

            if ($chunk === 0) {
                continue;
            }

            $chunkWords = $this->chunkToWords($chunk, $units, $tens);
            $parts[] = trim($chunkWords.' '.$scaleLabel);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, string>  $units
     * @param  array<int, string>  $tens
     */
    private function chunkToWords(int $number, array $units, array $tens): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = $units[intdiv($number, 100)].' hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = $tens[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0 && $number < 20) {
            $words[] = $units[$number];
        }

        return implode(' ', $words);
    }

    /**
     * @return array{
     *   metrics: array<string, array{label:string, value:string, hint:string, words?:string}>,
     *   roleSummaryCards: array<int, array{label:string,value:string,hint:string,tone:string}>,
     *   priorityActions: array<int, array{label:string,route:string,url:string,hint:string}>,
     *   recentSignals: array<int, array{label:string,time:string,detail:string}>
     * }
     */
    private function resolveDashboardSnapshot(User $user, int $companyId, string $currencyCode): array
    {
        if (! $this->canUsePerformanceCache()) {
            return $this->buildDashboardSnapshot($user, $companyId, $currencyCode);
        }

        $cacheTtl = max(5, (int) config('performance.cache.dashboard_ttl_seconds', 45));
        $cacheKey = $this->dashboardSnapshotCacheKey($user, $companyId, $currencyCode);

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtl), function () use ($user, $companyId, $currencyCode): array {
            return $this->buildDashboardSnapshot($user, $companyId, $currencyCode);
        });
    }

    /**
     * @return array{
     *   metrics: array<string, array{label:string, value:string, hint:string, words?:string}>,
     *   roleSummaryCards: array<int, array{label:string,value:string,hint:string,tone:string}>,
     *   priorityActions: array<int, array{label:string,route:string,url:string,hint:string}>,
     *   recentSignals: array<int, array{label:string,time:string,detail:string}>
     * }
     */
    private function buildDashboardSnapshot(User $user, int $companyId, string $currencyCode): array
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $departmentCount = Department::query()
            ->where('company_id', $companyId)
            ->count();
        $userCount = User::query()->where('company_id', $companyId)->count();
        $monthSpend = (int) Expense::query()
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $requestsInReviewCount = (int) SpendRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'in_review')
            ->count();
        $requestsInReviewValue = (int) SpendRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'in_review')
            ->sum('amount');
        $approvedThisMonthCount = (int) SpendRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $approvedThisMonthValue = (int) SpendRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('approved_amount');
        $activeBudgetBaseQuery = DepartmentBudget::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('period_start', '<=', today())
            ->whereDate('period_end', '>=', today());
        $activeBudgetCount = (int) (clone $activeBudgetBaseQuery)->count();
        $approvedBudgetTotal = (int) (clone $activeBudgetBaseQuery)->sum('allocated_amount');
        $budgetRemainingTotal = (int) (clone $activeBudgetBaseQuery)->sum('remaining_amount');
        $assetTotal = (int) Asset::query()
            ->where('company_id', $companyId)
            ->count();
        $assetAssigned = (int) Asset::query()
            ->where('company_id', $companyId)
            ->whereNotNull('assigned_to_user_id')
            ->where('status', '!=', Asset::STATUS_DISPOSED)
            ->count();
        $assetDisposed = (int) Asset::query()
            ->where('company_id', $companyId)
            ->where('status', Asset::STATUS_DISPOSED)
            ->count();

        $metrics = [
            'total_spend' => [
                'label' => 'Total Spend (This Month)',
                'value' => $this->formatMoney($monthSpend, $currencyCode),
                'hint' => sprintf('Posted expenses for %s', now()->format('F Y')),
                'words' => $this->formatAmountInWords($monthSpend, $currencyCode),
            ],
            'pending_approvals' => [
                'label' => 'Requests In Review',
                'value' => sprintf('%s requests', $this->formatCount($requestsInReviewCount)),
                'hint' => sprintf('Pipeline value: %s', $this->formatMoney($requestsInReviewValue, $currencyCode)),
                'words' => $this->formatAmountInWords($requestsInReviewValue, $currencyCode),
            ],
            'approved_value_month' => [
                'label' => 'Approved Value (This Month)',
                'value' => $this->formatMoney($approvedThisMonthValue, $currencyCode),
                'hint' => sprintf('%s approved requests this month', $this->formatCount($approvedThisMonthCount)),
                'words' => $this->formatAmountInWords($approvedThisMonthValue, $currencyCode),
            ],
            'approved_budget' => [
                'label' => 'Approved Budget (Active)',
                'value' => $this->formatMoney($approvedBudgetTotal, $currencyCode),
                'hint' => sprintf('%s active department budgets', $this->formatCount($activeBudgetCount)),
                'words' => $this->formatAmountInWords($approvedBudgetTotal, $currencyCode),
            ],
            'budget_remaining' => [
                'label' => 'Budget Remaining (Active)',
                'value' => $this->formatMoney($budgetRemainingTotal, $currencyCode),
                'hint' => 'Remaining balance across active budgets',
                'words' => $this->formatAmountInWords($budgetRemainingTotal, $currencyCode),
            ],
            'assets_overview' => [
                'label' => 'Assets Overview',
                'value' => sprintf(
                    '%s total / %s assigned / %s disposed',
                    $this->formatCount($assetTotal),
                    $this->formatCount($assetAssigned),
                    $this->formatCount($assetDisposed)
                ),
                'hint' => 'Custody and lifecycle tracking live',
            ],
            'departments' => [
                'label' => 'Departments',
                'value' => (string) $departmentCount,
                'hint' => 'Departments configured in your organization',
            ],
            'users' => [
                'label' => 'Users',
                'value' => (string) $userCount,
                'hint' => 'Active users in your organization',
            ],
        ];

        $this->roleSummaryCards = [];
        $this->priorityActions = [];
        $this->recentSignals = [];
        $this->buildRoleLens($user, $companyId, $currencyCode);

        return [
            'metrics' => $metrics,
            'roleSummaryCards' => $this->roleSummaryCards,
            'priorityActions' => $this->priorityActions,
            'recentSignals' => $this->recentSignals,
        ];
    }

    private function dashboardSnapshotCacheKey(User $user, int $companyId, string $currencyCode): string
    {
        $fingerprint = md5(json_encode([
            'company_id' => $companyId,
            'user_id' => (int) $user->id,
            'role' => (string) $this->roleView,
            'currency' => strtoupper($currencyCode),
        ]) ?: '');

        return 'flowdesk:dashboard:snapshot:'.$fingerprint;
    }

    private function canUsePerformanceCache(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return (bool) config('performance.cache.enabled', true);
    }

    private function formatMoney(int|float|string|null $amount, string $currencyCode): string
    {
        return Money::formatCurrency($amount, $currencyCode);
    }

    private function formatCount(int|float|string|null $value): string
    {
        return Money::formatCount($value);
    }
}
