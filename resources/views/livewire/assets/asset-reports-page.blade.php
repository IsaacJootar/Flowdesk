<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Asset Reports</p>
                <p class="mt-1 text-sm text-slate-600">Track asset allocation, maintenance spend, and disposal trends with filterable reports.</p>
            </div>
            <div class="inline-flex items-center gap-2">
                <button
                    type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    wire:target="exportCsv"
                    class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-slate-100 px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                    <span wire:loading wire:target="exportCsv">Exporting...</span>
                </button>
                <a
                    href="{{ route('assets.index') }}"
                    class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to Assets</span>
                </a>
            </div>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-6">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Asset code, name, serial"
                >
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Category</span>
                <select wire:model.live="categoryFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Assignee</span>
                <select wire:model.live="assigneeFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All assignees</option>
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
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
        </div>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">From</span>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">To</span>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Assets</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $metrics['total_assets']) }}</p>
            <p class="mt-1 text-xs text-sky-700">Current filtered inventory</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-indigo-700">Assigned</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ number_format((int) $metrics['assigned_assets']) }}</p>
            <p class="mt-1 text-xs text-indigo-700">Assets with active custody</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-100 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-slate-700">Unassigned</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((int) $metrics['unassigned_assets']) }}</p>
            <p class="mt-1 text-xs text-slate-700">Available inventory units</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Maintenance Cost</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $currencyCode }} {{ number_format((int) $metrics['maintenance_cost']) }}</p>
            <p class="mt-1 text-xs text-amber-700">Maintenance events in filter window</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Disposed</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $metrics['disposed_assets']) }}</p>
            <p class="mt-1 text-xs text-rose-700">Retired asset records</p>
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
            <div wire:loading.flex wire:target="search,statusFilter,categoryFilter,assigneeFilter,departmentFilter,dateFrom,dateTo,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading asset report rows...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Asset</th>
                            <th class="px-4 py-3 text-left font-semibold">Category</th>
                            <th class="px-4 py-3 text-left font-semibold">Assignee</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Acquired</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Maintenance Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assets as $asset)
                            @php
                                $statusClass = match ($asset->status) {
                                    'assigned' => 'bg-indigo-100 text-indigo-700',
                                    'in_maintenance' => 'bg-amber-100 text-amber-700',
                                    'disposed' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-emerald-100 text-emerald-700',
                                };
                            @endphp
                            <tr wire:key="asset-report-row-{{ $asset->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $asset->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $asset->category?->name ?? 'Uncategorized' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $asset->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $asset->assignedDepartment?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ optional($asset->acquisition_date)->format('M d, Y') ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucwords(str_replace('_', ' ', (string) $asset->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $currencyCode }} {{ number_format((int) ($asset->maintenance_total ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No asset report rows found for current filters.
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
                        <span class="font-semibold text-slate-700">{{ $assets->firstItem() ?? 0 }}</span>
                        -
                        <span class="font-semibold text-slate-700">{{ $assets->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-semibold text-slate-700">{{ $assets->total() }}</span>
                    </p>
                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled($assets->onFirstPage())
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <span class="text-xs text-slate-500">
                            Page {{ $assets->currentPage() }} of {{ $assets->lastPage() }}
                        </span>
                        <button
                            type="button"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            wire:target="previousPage,nextPage,gotoPage"
                            @disabled(! $assets->hasMorePages())
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

