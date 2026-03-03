<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Unified Reports Center</p>
                <p class="mt-1 text-sm text-slate-600">Monitor requests, expenses, vendors, procurement, treasury, assets, and budgets from one reporting surface.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($quickLinks as $link)
                    <a
                        href="{{ $link['route'] }}"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Module</span>
                <select wire:model.live="moduleFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($moduleOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Code, title, user, department"
                >
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">From</span>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">To</span>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>

            <div class="sm:col-span-2 lg:col-span-6">
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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
        @if (! $readyToLoad)
            @for ($i = 0; $i < 7; $i++)
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-2 h-3 w-24 animate-pulse rounded bg-slate-200"></div>
                    <div class="mb-2 h-7 w-20 animate-pulse rounded bg-slate-200"></div>
                    <div class="h-3 w-28 animate-pulse rounded bg-slate-200"></div>
                </div>
            @endfor
        @else
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Requests</p>
                <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $metrics['requests']['total']) }}</p>
                <p class="mt-1 text-xs text-sky-700">In review: {{ number_format((int) $metrics['requests']['in_review']) }}</p>
                <p class="text-xs text-sky-700">{{ $currencyCode }} {{ number_format((int) $metrics['requests']['amount']) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Posted Expenses</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $metrics['expenses']['posted']) }}</p>
                <p class="mt-1 text-xs text-emerald-700">Void: {{ number_format((int) $metrics['expenses']['void']) }}</p>
                <p class="text-xs text-emerald-700">{{ $currencyCode }} {{ number_format((int) $metrics['expenses']['amount']) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Vendor Outstanding</p>
                @if ($canViewVendors)
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $metrics['vendors']['outstanding_count']) }}</p>
                    <p class="mt-1 text-xs text-amber-700">Overdue: {{ number_format((int) $metrics['vendors']['overdue_count']) }}</p>
                    <p class="text-xs text-amber-700">{{ $currencyCode }} {{ number_format((int) $metrics['vendors']['outstanding_amount']) }}</p>
                @else
                    <p class="mt-1 text-sm font-semibold text-amber-900">Access restricted</p>
                    <p class="mt-1 text-xs text-amber-700">Vendor invoice metrics are available to finance, auditor, and owner roles.</p>
                @endif
            </div>
            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-orange-700">Procurement Controls</p>
                <p class="mt-1 text-2xl font-semibold text-orange-900">{{ number_format((int) $metrics['procurement']['linked_invoices']) }}</p>
                <p class="mt-1 text-xs text-orange-700">Open exceptions: {{ number_format((int) $metrics['procurement']['open_exceptions']) }}</p>
                <p class="text-xs text-orange-700">Match pass rate: {{ number_format((float) $metrics['procurement']['match_pass_rate_percent'], 1) }}%</p>
                <p class="text-xs text-orange-700">Stale commitments: {{ number_format((int) $metrics['procurement']['stale_commitments']) }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-indigo-700">Assets</p>
                <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ number_format((int) $metrics['assets']['total']) }}</p>
                <p class="mt-1 text-xs text-indigo-700">Assigned: {{ number_format((int) $metrics['assets']['assigned']) }}</p>
                <p class="text-xs text-indigo-700">Maintenance: {{ number_format((int) $metrics['assets']['in_maintenance']) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-300 bg-slate-100 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-slate-700">Active Budgets</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((int) $metrics['budgets']['active_count']) }}</p>
                <p class="mt-1 text-xs text-slate-700">Remaining: {{ $currencyCode }} {{ number_format((int) $metrics['budgets']['remaining']) }}</p>
                <p class="text-xs text-slate-700">Allocated: {{ $currencyCode }} {{ number_format((int) $metrics['budgets']['allocated']) }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                <p class="text-xs uppercase tracking-[0.1em] text-cyan-700">Treasury Reconciliation</p>
                <p class="mt-1 text-2xl font-semibold text-cyan-900">{{ number_format((int) $metrics['treasury']['reconciled_lines']) }}</p>
                <p class="mt-1 text-xs text-cyan-700">Open exceptions: {{ number_format((int) $metrics['treasury']['open_exceptions']) }}</p>
                <p class="text-xs text-cyan-700">Unreconciled value: {{ $currencyCode }} {{ number_format((int) $metrics['treasury']['unreconciled_value']) }}</p>
            </div>
        @endif
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="moduleFilter,search,departmentFilter,dateFrom,dateTo,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading unified report rows...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Module</th>
                            <th class="px-4 py-3 text-left font-semibold">Record</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Owner</th>
                            <th class="px-4 py-3 text-left font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Updated</th>
                            <th class="px-4 py-3 text-left font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($activities as $activity)
                            @php
                                $moduleClass = match ($activity['module']) {
                                    'Requests' => 'bg-sky-100 text-sky-700',
                                    'Expenses' => 'bg-emerald-100 text-emerald-700',
                                    'Vendors' => 'bg-amber-100 text-amber-700',
                                    'Procurement' => 'bg-orange-100 text-orange-700',
                                    'Assets' => 'bg-indigo-100 text-indigo-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };

                                $statusClass = 'bg-slate-100 text-slate-700';
                                if (in_array((string) $activity['status'], ['approved', 'posted', 'paid', 'active', 'assigned', 'resolved', 'waived'], true)) {
                                    $statusClass = 'bg-emerald-100 text-emerald-700';
                                } elseif (in_array((string) $activity['status'], ['in_review', 'part_paid', 'in_maintenance'], true)) {
                                    $statusClass = 'bg-amber-100 text-amber-700';
                                } elseif (in_array((string) $activity['status'], ['rejected', 'void', 'disposed', 'open'], true)) {
                                    $statusClass = 'bg-red-100 text-red-700';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50" wire:key="unified-report-row-{{ $activity['module'] }}-{{ $activity['code'] }}-{{ $loop->index }}">
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $moduleClass }}">
                                        {{ $activity['module'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $activity['title'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $activity['code'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $activity['department'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $activity['owner'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $currencyCode }} {{ number_format((int) ($activity['amount'] ?? 0)) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $activity['status'])) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($activity['occurred_at'])->format('M d, Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <a
                                        href="{{ $activity['url'] }}"
                                        class="inline-flex rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No report rows found for the selected filters.
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
                        <span class="font-semibold text-slate-700">{{ $activities->firstItem() ?? 0 }}</span>
                        -
                        <span class="font-semibold text-slate-700">{{ $activities->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-semibold text-slate-700">{{ $activities->total() }}</span>
                    </p>

                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled($activities->onFirstPage())
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <span class="text-xs text-slate-500">
                            Page {{ $activities->currentPage() }} of {{ $activities->lastPage() }}
                        </span>
                        <button
                            type="button"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled(! $activities->hasMorePages())
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



