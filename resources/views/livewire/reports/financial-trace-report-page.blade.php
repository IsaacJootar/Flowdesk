<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reports</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">Budget to Payment Trace</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.index') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reports</a>
                <a href="{{ route('requests.reports') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Request Reports</a>
                <a href="{{ route('reports.financial-trace-help') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-700 bg-slate-700 px-3 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Trace Guide</a>
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

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,statusFilter,paymentFilter,departmentFilter,dateFrom,dateTo,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading trace rows...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Request</th>
                            <th class="px-4 py-3 text-left font-semibold">Budget</th>
                            <th class="px-4 py-3 text-left font-semibold">Approval</th>
                            <th class="px-4 py-3 text-left font-semibold">Order / Commitment</th>
                            <th class="px-4 py-3 text-left font-semibold">Payment</th>
                            <th class="px-4 py-3 text-left font-semibold">Expense</th>
                            <th class="px-4 py-3 text-left font-semibold">Bank Match</th>
                            <th class="px-4 py-3 text-left font-semibold">Audit</th>
                            <th class="px-4 py-3 text-left font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
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
                            @endphp
                            <tr class="align-top hover:bg-slate-50" wire:key="financial-trace-report-row-{{ $row['id'] }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $row['title'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['request_code'] }} &middot; {{ $row['department'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['requester'] }} &middot; {{ $row['vendor'] }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $traceStatusClass }}">
                                            {{ $row['trace_status'] }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $requestStatusClass }}">
                                            {{ ucfirst(str_replace('_', ' ', (string) $row['request_status'])) }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-700">{{ \App\Support\Money::formatCurrency((int) $row['amount'], (string) $row['currency']) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['budget_status'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['approval_status'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['purchase_order_status'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentClass }}">
                                        {{ $row['payment_status'] }}
                                    </span>
                                    @if ($row['payment_method'] !== '-')
                                        <p class="mt-1 text-xs text-slate-500">{{ $row['payment_method'] }}</p>
                                    @endif
                                    @if ($row['payment_reference'] !== '')
                                        <p class="mt-1 text-xs text-slate-500">{{ $row['payment_reference'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['expense_status'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $bankClass }}">
                                        {{ $row['reconciliation_status'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['audit_status'] }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $row['url'] }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Open
                                    </a>
                                </td>
                            </tr>
                            @if (count($row['gaps']) > 0)
                                <tr wire:key="financial-trace-report-gaps-{{ $row['id'] }}">
                                    <td colspan="9" class="bg-slate-50 px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($row['gaps'] as $gap)
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ ($gap['severity'] ?? '') === 'high' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ $gap['label'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No trace rows found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
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
