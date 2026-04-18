<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reports</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">Budget to Payment Trace</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.index') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reports<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('requests.reports') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Request Reports<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('reports.financial-trace-help') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-700 bg-slate-700 px-3 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Trace Guide<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Code, title, requester, vendor"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Request Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Trace Status</span>
                <select wire:model.live="traceStatusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All trace statuses</option>
                    @foreach ($traceStatusOptions as $status => $label)
                        <option value="{{ $status }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Status</span>
                <select wire:model.live="paymentFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All payments</option>
                    @foreach ($paymentOptions as $status)
                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department</span>
                <select wire:model.live="departmentFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2 lg:col-span-6 lg:grid-cols-5">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">From</span>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">To</span>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </label>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                        <span>Rows</span>
                        <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Requests</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $metrics['total_requests']) }}</p>
            <p class="mt-1 text-xs text-sky-700">{{ \App\Support\Money::formatCurrency((int) $metrics['total_amount'], $currencyCode) }}</p>
        </div>
        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-cyan-700">Payment Attempts</p>
            <p class="mt-1 text-2xl font-semibold text-cyan-900">{{ number_format((int) $metrics['payment_attempts']) }}</p>
            <p class="mt-1 text-xs text-cyan-700">Settled: {{ number_format((int) $metrics['settled_payments']) }}</p>
        </div>
        <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-orange-700">Purchase Orders</p>
            <p class="mt-1 text-2xl font-semibold text-orange-900">{{ number_format((int) $metrics['purchase_orders']) }}</p>
            <p class="mt-1 text-xs text-orange-700">Linked to requests</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Expense Records</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $metrics['linked_expenses']) }}</p>
            <p class="mt-1 text-xs text-emerald-700">Linked to requests</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Trace Notes</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $metrics['trace_notes_on_page']) }}</p>
            <p class="mt-1 text-xs text-amber-700">Current page</p>
        </div>
    </div>

    <div class="space-y-3">
        @if (! $readyToLoad)
            <div class="fd-card space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,statusFilter,traceStatusFilter,paymentFilter,departmentFilter,dateFrom,dateTo,perPage,gotoPage,previousPage,nextPage" class="fd-card px-4 py-3 text-sm text-slate-500">
                Loading trace rows...
            </div>

            <div class="space-y-3">
                @forelse ($traceRows as $row)
                    @php
                        $requestStatusClass = 'bg-slate-100 text-slate-700';
                        if (in_array($row['request_status'], ['approved', 'settled'], true)) {
                            $requestStatusClass = 'bg-emerald-100 text-emerald-700';
                        } elseif (in_array($row['request_status'], ['failed', 'reversed', 'rejected'], true)) {
                            $requestStatusClass = 'bg-red-100 text-red-700';
                        } elseif (in_array($row['request_status'], ['in_review', 'execution_queued', 'execution_processing'], true)) {
                            $requestStatusClass = 'bg-amber-100 text-amber-700';
                        }

                        $paymentClass = 'bg-slate-100 text-slate-700';
                        if ($row['payment_status'] === 'Settled') {
                            $paymentClass = 'bg-emerald-100 text-emerald-700';
                        } elseif (in_array($row['payment_status'], ['Failed', 'Reversed'], true)) {
                            $paymentClass = 'bg-red-100 text-red-700';
                        } elseif (in_array($row['payment_status'], ['Queued', 'Processing'], true)) {
                            $paymentClass = 'bg-amber-100 text-amber-700';
                        }

                        $bankClass = $row['reconciliation_status'] === 'Matched'
                            ? 'bg-emerald-100 text-emerald-700'
                            : ($row['reconciliation_status'] === 'Not matched' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-700');

                        $traceStatusClass = $row['trace_severity'] === 'high'
                            ? 'bg-red-100 text-red-700'
                            : ($row['trace_severity'] === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');

                        $requestStatusLabel = ucwords(str_replace('_', ' ', (string) $row['request_status']));

                        // Pipeline item color classifier — drives left border + label + value colours
                        $pipelineColor = static function (string $value): string {
                            $v = strtolower($value);
                            if (str_contains($v, 'failed') || str_contains($v, 'reversed') || str_contains($v, 'over budget') || str_contains($v, 'rejected') || str_contains($v, 'returned') || str_contains($v, 'open issues')) {
                                return 'fail';
                            }
                            if (str_contains($v, 'within budget') || str_contains($v, 'settled') || str_contains($v, 'matched') || str_contains($v, 'approved')) {
                                return 'success';
                            }
                            if (str_contains($v, 'queued') || str_contains($v, 'processing')) {
                                return 'pending';
                            }
                            return 'neutral';
                        };

                        $pipelineClasses = [
                            'fail'    => ['border' => 'border-red-400',     'label' => 'text-red-500',     'value' => 'text-red-700'],
                            'success' => ['border' => 'border-emerald-300', 'label' => 'text-emerald-600', 'value' => 'text-emerald-800'],
                            'pending' => ['border' => 'border-amber-300',   'label' => 'text-amber-500',   'value' => 'text-amber-800'],
                            'neutral' => ['border' => 'border-slate-200',   'label' => 'text-slate-400',   'value' => 'text-slate-800'],
                        ];

                        $budgetPc   = $pipelineClasses[$pipelineColor($row['budget_status'])];
                        $approvalPc = $pipelineClasses[$pipelineColor($row['approval_status'])];
                        $orderPc    = $pipelineClasses[$pipelineColor($row['purchase_order_status'])];
                        $paymentPc  = $pipelineClasses[$pipelineColor($row['payment_status'])];
                        $expensePc  = $pipelineClasses[$pipelineColor($row['expense_status'])];
                        $bankPc     = $pipelineClasses[$pipelineColor($row['reconciliation_status'])];
                        $auditPc    = $pipelineClasses['neutral'];
                    @endphp

                    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" wire:key="financial-trace-report-row-{{ $row['id'] }}">

                        {{-- Header: request identity + amount + status badges --}}
                        <div class="p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">{{ $row['request_code'] }}</p>
                                    <h2 class="mt-1 break-words text-base font-semibold leading-6 text-slate-900">{{ $row['title'] }}</h2>
                                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                        <div class="min-w-0">
                                            <dt class="text-xs font-medium text-slate-400">Department</dt>
                                            <dd class="mt-0.5 break-words font-medium text-slate-700">{{ $row['department'] }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="text-xs font-medium text-slate-400">Requester</dt>
                                            <dd class="mt-0.5 break-words font-medium text-slate-700">{{ $row['requester'] }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="text-xs font-medium text-slate-400">Vendor</dt>
                                            <dd class="mt-0.5 break-words font-medium text-slate-700">{{ $row['vendor'] }}</dd>
                                        </div>
                                    </dl>
                                </div>
                                <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                                    <p class="text-base font-semibold text-slate-900">{{ \App\Support\Money::formatCurrency((int) $row['amount'], (string) $row['currency']) }}</p>
                                    <div class="flex flex-wrap gap-1.5 sm:justify-end">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $traceStatusClass }}">{{ $row['trace_status'] }}</span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $requestStatusClass }}">{{ $requestStatusLabel }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Trace pipeline --}}
                        <div class="grid gap-x-4 gap-y-3 border-t border-slate-100 bg-slate-50/60 px-4 py-4 md:grid-cols-4 xl:grid-cols-7">
                            <div class="min-w-0 border-l-2 {{ $budgetPc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $budgetPc['label'] }}">Budget</p>
                                <p class="mt-1 break-words text-sm font-medium {{ $budgetPc['value'] }}">{{ $row['budget_status'] }}</p>
                            </div>
                            <div class="min-w-0 border-l-2 {{ $approvalPc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $approvalPc['label'] }}">Approval</p>
                                <p class="mt-1 break-words text-sm font-medium {{ $approvalPc['value'] }}">{{ $row['approval_status'] }}</p>
                            </div>
                            <div class="min-w-0 border-l-2 {{ $orderPc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $orderPc['label'] }}">Order</p>
                                <p class="mt-1 break-words text-sm font-medium {{ $orderPc['value'] }}">{{ $row['purchase_order_status'] }}</p>
                            </div>
                            <div class="min-w-0 border-l-2 {{ $paymentPc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $paymentPc['label'] }}">Payment</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentClass }}">{{ $row['payment_status'] }}</span>
                                </div>
                                @if ($row['payment_method'] !== '-')
                                    <p class="mt-1.5 break-words text-xs text-slate-600">{{ $row['payment_method'] }}</p>
                                @endif
                                @if ($row['payment_reference'] !== '')
                                    <p class="mt-0.5 break-words text-xs text-slate-400">{{ $row['payment_reference'] }}</p>
                                @endif
                            </div>
                            <div class="min-w-0 border-l-2 {{ $expensePc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $expensePc['label'] }}">Expense</p>
                                <p class="mt-1 break-words text-sm font-medium {{ $expensePc['value'] }}">{{ $row['expense_status'] }}</p>
                            </div>
                            <div class="min-w-0 border-l-2 {{ $bankPc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $bankPc['label'] }}">Bank Match</p>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $bankClass }}">{{ $row['reconciliation_status'] }}</span>
                            </div>
                            <div class="min-w-0 border-l-2 {{ $auditPc['border'] }} py-1 pl-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $auditPc['label'] }}">Audit</p>
                                <p class="mt-1 break-words text-sm font-medium {{ $auditPc['value'] }}">{{ $row['audit_status'] }}</p>
                            </div>
                        </div>

                        {{-- Gaps (optional) --}}
                        @if (count($row['gaps']) > 0)
                            <div class="border-t border-slate-100 px-4 py-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Needs Attention</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($row['gaps'] as $gap)
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ ($gap['severity'] ?? '') === 'high' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $gap['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Footer: Open request (always last) --}}
                        <div class="flex items-center justify-end border-t border-slate-100 px-4 py-3">
                            <a href="{{ $row['url'] }}" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                View request
                            </a>
                        </div>

                    </article>
                @empty
                    <div class="fd-card px-4 py-10 text-center text-sm text-slate-500">
                        No trace rows found for the selected filters.
                    </div>
                @endforelse
            </div>

            <div class="fd-card px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <p class="text-slate-500">
                        Showing
                        <span class="font-semibold text-slate-700">{{ $requests->firstItem() ?? 0 }}</span>
                        -
                        <span class="font-semibold text-slate-700">{{ $requests->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-semibold text-slate-700">{{ $requests->total() }}</span>
                    </p>

                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled($requests->onFirstPage())
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <span class="text-xs text-slate-500">
                            Page {{ $requests->currentPage() }} of {{ $requests->lastPage() }}
                        </span>
                        <button
                            type="button"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled(! $requests->hasMorePages())
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
