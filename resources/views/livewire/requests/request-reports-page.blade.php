<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-6">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Code, title, requester"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Type</span>
                <select wire:model.live="typeFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All types</option>
                    @foreach ($requestTypes as $type)
                        <option value="{{ $type->code }}">{{ $type->name }}</option>
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

            <div class="grid gap-3 sm:grid-cols-2 lg:col-span-6 lg:grid-cols-5">
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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Requests</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $metrics['total_requests']) }}</p>
            <p class="mt-1 text-xs text-sky-700">Total amount: {{ number_format((int) $metrics['total_amount']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">In Review</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $metrics['in_review']) }}</p>
            <p class="mt-1 text-xs text-amber-700">Pending decision workload</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Approval Rate</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((float) $metrics['approval_rate'], 1) }}%</p>
            <p class="mt-1 text-xs text-emerald-700">Approved / final decisions</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-indigo-700">Avg Decision Time</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ number_format((float) $metrics['avg_decision_hours'], 1) }}h</p>
            <p class="mt-1 text-xs text-indigo-700">From submit to final decision</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Overdue Steps</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $metrics['overdue_steps']) }}</p>
            <p class="mt-1 text-xs text-rose-700">Pending past response timing</p>
        </div>
        <div class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-fuchsia-700">Escalated Steps</p>
            <p class="mt-1 text-2xl font-semibold text-fuchsia-900">{{ number_format((int) $metrics['escalated_steps']) }}</p>
            <p class="mt-1 text-xs text-fuchsia-700">Escalation triggered by response timing</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="fd-card p-4 lg:col-span-2">
            <p class="text-sm font-semibold text-slate-900">Status Breakdown</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($statuses as $status)
                    @php
                        $count = (int) ($statusBreakdown[$status] ?? 0);
                        $statusClass = 'bg-slate-100 text-slate-700';
                        if ($status === 'approved') {
                            $statusClass = 'bg-emerald-100 text-emerald-700';
                        } elseif ($status === 'rejected') {
                            $statusClass = 'bg-red-100 text-red-700';
                        } elseif ($status === 'in_review') {
                            $statusClass = 'bg-amber-100 text-amber-700';
                        } elseif ($status === 'returned') {
                            $statusClass = 'bg-indigo-100 text-indigo-700';
                        }
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                        <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                        <span>{{ $count }}</span>
                    </span>
                @endforeach
            </div>
        </div>

        <div class="fd-card p-4">
            <p class="text-sm font-semibold text-slate-900">Top Departments</p>
            <div class="mt-3 space-y-2">
                @forelse ($topDepartments as $row)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-sm font-medium text-slate-800">{{ $row->department?->name ?? 'Unassigned' }}</p>
                        <p class="text-xs text-slate-500">{{ number_format((int) $row->total_requests) }} requests · {{ number_format((int) $row->total_amount) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No department activity in current filter.</p>
                @endforelse
            </div>
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
            <div wire:loading.flex wire:target="search,statusFilter,typeFilter,departmentFilter,dateFrom,dateTo,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading request report rows...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Request</th>
                            <th class="px-4 py-3 text-left font-semibold">Requester</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Submitted</th>
                            <th class="px-4 py-3 text-left font-semibold">Decided</th>
                            <th class="px-4 py-3 text-left font-semibold">Cycle Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($requests as $request)
                            @php
                                $statusClass = 'bg-slate-100 text-slate-700';
                                if ($request->status === 'approved') {
                                    $statusClass = 'bg-emerald-100 text-emerald-700';
                                } elseif ($request->status === 'rejected') {
                                    $statusClass = 'bg-red-100 text-red-700';
                                } elseif ($request->status === 'in_review') {
                                    $statusClass = 'bg-amber-100 text-amber-700';
                                } elseif ($request->status === 'returned') {
                                    $statusClass = 'bg-indigo-100 text-indigo-700';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50" wire:key="request-report-{{ $request->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $request->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $request->request_code }} · {{ (string) (($request->metadata['request_type_name'] ?? null) ?: ucfirst((string) (($request->metadata['type'] ?? 'spend')))) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $request->requester?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $request->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ strtoupper((string) $request->currency) }} {{ number_format((int) $request->amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $request->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($request->submitted_at ?? $request->created_at)->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($request->decided_at)->format('M d, Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($request->submitted_at && $request->decided_at)
                                        @php($cycleMinutes = max(0, (int) $request->submitted_at->diffInMinutes($request->decided_at, false)))
                                        {{ number_format($cycleMinutes / 60, 1) }}h
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No request report data found for the selected filters.
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
