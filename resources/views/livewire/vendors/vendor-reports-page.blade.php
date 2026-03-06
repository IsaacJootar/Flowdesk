<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor Reports</p>
                <p class="mt-1 text-sm text-slate-600">Trace vendor-linked and request-linked spend with aging and reconciliation insights.</p>
            </div>
            <a
                href="{{ route('vendors.index') }}"
                class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                <span aria-hidden="true">&larr;</span>
                <span>Back to Vendor Management</span>
            </a>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="space-y-3">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-6">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="Expense code, title, vendor, request code"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor Link</span>
                    <select wire:model.live="vendorLinkFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All</option>
                        <option value="linked">Linked</option>
                        <option value="unlinked">Unlinked</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Request Link</span>
                    <select wire:model.live="requestLinkFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All</option>
                        <option value="linked">Linked</option>
                        <option value="unlinked">Unlinked</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                    <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
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

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor</span>
                    <select wire:model.live="vendorFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All vendors</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-6">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">From</span>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">To</span>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Amount Min</span>
                    <input type="number" min="0" wire:model.live.debounce.300ms="amountMin" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="0">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Amount Max</span>
                    <input type="number" min="0" wire:model.live.debounce.300ms="amountMax" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Any">
                </label>

                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                        <span>Rows</span>
                        <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>
                </div>

                <div aria-hidden="true"></div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Expenses</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $metrics['total_count']) }}</p>
            <p class="mt-1 text-xs text-sky-700">NGN {{ number_format((int) $metrics['total_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Vendor Linked</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $metrics['vendor_linked_count']) }}</p>
            <p class="mt-1 text-xs text-emerald-700">NGN {{ number_format((int) $metrics['vendor_linked_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Vendor Unlinked</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $metrics['vendor_unlinked_count']) }}</p>
            <p class="mt-1 text-xs text-amber-700">NGN {{ number_format((int) $metrics['vendor_unlinked_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-indigo-700">Request Linked</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ number_format((int) $metrics['request_linked_count']) }}</p>
            <p class="mt-1 text-xs text-indigo-700">NGN {{ number_format((int) $metrics['request_linked_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-fuchsia-700">Fully Linked</p>
            <p class="mt-1 text-2xl font-semibold text-fuchsia-900">{{ number_format((int) $metrics['fully_linked_count']) }}</p>
            <p class="mt-1 text-xs text-fuchsia-700">Request + Expense + Vendor</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-slate-600">Outstanding Invoices</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((int) $agingMetrics['outstanding_count']) }}</p>
            <p class="mt-1 text-xs text-slate-700">NGN {{ number_format((int) $agingMetrics['outstanding_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Overdue 0-30 Days</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $agingMetrics['overdue_0_30_count']) }}</p>
            <p class="mt-1 text-xs text-amber-700">NGN {{ number_format((int) $agingMetrics['overdue_0_30_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-orange-700">Overdue 31-60 Days</p>
            <p class="mt-1 text-2xl font-semibold text-orange-900">{{ number_format((int) $agingMetrics['overdue_31_60_count']) }}</p>
            <p class="mt-1 text-xs text-orange-700">NGN {{ number_format((int) $agingMetrics['overdue_31_60_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Overdue 61+ Days</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $agingMetrics['overdue_61_plus_count']) }}</p>
            <p class="mt-1 text-xs text-rose-700">NGN {{ number_format((int) $agingMetrics['overdue_61_plus_amount']) }}</p>
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
            <div wire:loading.flex wire:target="search,vendorLinkFilter,requestLinkFilter,statusFilter,departmentFilter,vendorFilter,dateFrom,dateTo,amountMin,amountMax,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading traceability rows...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Expense</th>
                            <th class="px-4 py-3 text-left font-semibold">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold">Request</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">Date</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Trace Path</th>
                            <th class="px-4 py-3 text-left font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($expenses as $expense)
                            @php
                                $statusClass = $expense->status === 'void'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-emerald-100 text-emerald-700';

                                $traceClass = 'bg-slate-100 text-slate-700';
                                $traceLabel = 'Direct Expense';

                                if ($expense->request_id && $expense->vendor_id) {
                                    $traceClass = 'bg-fuchsia-100 text-fuchsia-700';
                                    $traceLabel = 'Request -> Expense -> Vendor';
                                } elseif ($expense->request_id && ! $expense->vendor_id) {
                                    $traceClass = 'bg-indigo-100 text-indigo-700';
                                    $traceLabel = 'Request -> Expense (No Vendor)';
                                } elseif (! $expense->request_id && $expense->vendor_id) {
                                    $traceClass = 'bg-emerald-100 text-emerald-700';
                                    $traceLabel = 'Direct Expense -> Vendor';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50" wire:key="vendor-report-expense-{{ $expense->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $expense->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $expense->expense_code }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if ($expense->vendor)
                                        <a
                                            href="{{ route('vendors.show', ['vendor' => $expense->vendor_id]) }}"
                                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200"
                                        >
                                            {{ $expense->vendor->name }}
                                        </a>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">Unlinked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if ($expense->request_id)
                                        <p class="font-medium">{{ $expense->request?->request_code ?? ('REQ #'.$expense->request_id) }}</p>
                                        <p class="text-xs text-slate-500">{{ $expense->request?->title ?? 'Request record not currently active' }}</p>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">No request</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $expense->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">NGN {{ number_format((int) $expense->amount) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ optional($expense->expense_date)->format('M d, Y') ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst((string) $expense->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $traceClass }}">
                                        {{ $traceLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <a
                                            href="{{ route('expenses.index', ['search' => $expense->expense_code]) }}"
                                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200"
                                        >
                                            Open in Expenses
                                        </a>
                                        @if ($expense->request_id)
                                            <a
                                                href="{{ route('requests.index', ['open_request_id' => $expense->request_id]) }}"
                                                class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-200"
                                            >
                                                Open in Requests
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No vendor traceability records found for current filters.
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
                        <span class="font-semibold text-slate-700">{{ $expenses->firstItem() ?? 0 }}</span>
                        -
                        <span class="font-semibold text-slate-700">{{ $expenses->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-semibold text-slate-700">{{ $expenses->total() }}</span>
                    </p>

                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled($expenses->onFirstPage())
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <span class="text-xs text-slate-500">
                            Page {{ $expenses->currentPage() }} of {{ $expenses->lastPage() }}
                        </span>
                        <button
                            type="button"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled(! $expenses->hasMorePages())
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



