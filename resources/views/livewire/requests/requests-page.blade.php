<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="requests"
        title="Spend Requests"
        description="This is where your team submits requests for company funds — travel, vendor payments, reimbursements, and one-off purchases."
        :bullets="[
            'Submit a new request and it goes straight to the right approver.',
            'Track every request from submitted to paid — all in one list.',
            'Approvers get notified automatically; no chasing required.',
        ]"
    />
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="request-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif
        @if ($feedbackWarning)
            <div
                wire:key="request-feedback-warning-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg"
            >
                {{ $feedbackWarning }}
            </div>
        @endif
        @if ($feedbackError)
            <div
                wire:key="request-feedback-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
</div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-6">
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Scope</span>
                <select wire:model.live="scopeFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All accessible requests</option>
                    <option value="mine">My requests</option>
                    <option value="pending_my_approval">Awaiting my approval</option>
                    <option value="decided_by_me">Decided by me</option>
                </select>
                <span class="mt-1 block text-[11px] text-slate-500">Choose what you want to review right now.</span>
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Purchase Order</span>
                <select wire:model.live="poFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All requests</option>
                    <option value="with_po">Has a Purchase Order</option>
                    <option value="without_po">No Purchase Order yet</option>
                </select>
            </label>
        </div>

        <div class="mt-3 grid gap-3 lg:grid-cols-6">
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

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rows</span>
                <select wire:model.live="perPage" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </label>

            <div class="lg:col-span-2">
                <span class="mb-1 block select-none text-xs font-semibold uppercase tracking-[0.14em] text-transparent">Actions</span>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a
                        href="{{ route('requests.communications') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path>
                        </svg>
                        <span>Inbox & Logs</span>
                    </a>
                    <a
                        href="{{ route('requests.lifecycle-desk') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100"
                    >
                        <svg class="h-4 w-4 text-indigo-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 6h16"></path>
                            <path d="M4 12h16"></path>
                            <path d="M4 18h16"></path>
                        </svg>
                        <span>Progress Desk</span>
                    </a>
                    @can('create', \App\Domains\Requests\Models\SpendRequest::class)
                        <button
                            type="button"
                            wire:click="openCreateModal"
                            wire:loading.attr="disabled"
                            wire:target="openCreateModal"
                            class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="openCreateModal" class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                <span>New Request</span>
                            </span>
                            <span wire:loading wire:target="openCreateModal">Opening...</span>
                        </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Requests</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) ($requestAnalytics['total_requests'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-sky-700">Filtered amount: {{ number_format((int) ($requestAnalytics['total_amount'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Pending My Action</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) ($requestAnalytics['pending_my_action'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-amber-700">Requests currently awaiting your approval</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Status Breakdown</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($statuses as $status)
                    @php
                        $statusCount = (int) (($requestAnalytics['status_counts'][$status] ?? 0));
                        $statusClass = 'bg-slate-100 text-slate-700';
                        if (in_array($status, ['approved', 'settled'], true)) {
                            $statusClass = 'bg-emerald-100 text-emerald-700';
                        } elseif (in_array($status, ['rejected', 'failed', 'reversed'], true)) {
                            $statusClass = 'bg-red-100 text-red-700';
                        } elseif ($status === 'in_review') {
                            $statusClass = 'bg-amber-100 text-amber-700';
                        } elseif ($status === 'returned') {
                            $statusClass = 'bg-indigo-100 text-indigo-700';
                        } elseif ($status === 'approved_for_execution') {
                            $statusClass = 'bg-cyan-100 text-cyan-700';
                        } elseif ($status === 'execution_queued') {
                            $statusClass = 'bg-sky-100 text-sky-700';
                        } elseif ($status === 'execution_processing') {
                            $statusClass = 'bg-violet-100 text-violet-700';
                        }
                    @endphp
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold {{ $statusClass }}">
                        <span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                        <span>{{ $statusCount }}</span>
                    </span>
                @endforeach
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($requestTypes as $type)
                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200">
                        <span>{{ $type->name }}</span>
                        <span>{{ (int) (($requestAnalytics['type_counts'][$type->code] ?? 0)) }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,statusFilter,typeFilter,departmentFilter,scopeFilter,poFilter,dateFrom,dateTo,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading requests...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Request</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Requester</th>
                            <th class="px-4 py-3 text-left font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Approval Stage</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($requests as $request)
                            @php
                                $statusClass = 'bg-slate-100 text-slate-700';
                                if (in_array((string) $request->status, ['approved', 'settled'], true)) {
                                    $statusClass = 'bg-emerald-100 text-emerald-700';
                                } elseif (in_array((string) $request->status, ['rejected', 'failed', 'reversed'], true)) {
                                    $statusClass = 'bg-red-100 text-red-700';
                                } elseif ($request->status === 'in_review') {
                                    $statusClass = 'bg-amber-100 text-amber-700';
                                } elseif ($request->status === 'returned') {
                                    $statusClass = 'bg-indigo-100 text-indigo-700';
                                } elseif ($request->status === 'approved_for_execution') {
                                    $statusClass = 'bg-cyan-100 text-cyan-700';
                                } elseif ($request->status === 'execution_queued') {
                                    $statusClass = 'bg-sky-100 text-sky-700';
                                } elseif ($request->status === 'execution_processing') {
                                    $statusClass = 'bg-violet-100 text-violet-700';
                                }
                            @endphp
                            <tr wire:key="request-{{ $request->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $request->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $request->request_code }} &middot; {{ (string) (($request->metadata['request_type_name'] ?? null) ?: ucfirst((string) (($request->metadata['type'] ?? 'spend')))) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $request->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div class="flex items-center gap-2.5">
                                        @if ($request->requester?->avatar_path)
                                            <div class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                <img
                                                    src="{{ route('users.avatar', ['user' => $request->requester->id, 'v' => optional($request->requester->updated_at)->timestamp]) }}"
                                                    alt="{{ $request->requester->name }}"
                                                    class="h-full w-full object-cover"
                                                    style="object-position: center 30%;"
                                                    loading="lazy"
                                                >
                                            </div>
                                        @else
                                            @php
                                                $requesterParts = preg_split('/\s+/', trim((string) ($request->requester?->name ?? ''))) ?: [];
                                                $requesterInitials = ((isset($requesterParts[0][0]) ? strtoupper($requesterParts[0][0]) : '').(isset($requesterParts[1][0]) ? strtoupper($requesterParts[1][0]) : '')) ?: '?';
                                                $requesterGender = strtolower((string) ($request->requester?->gender ?? 'other'));
                                                $requesterAvatarBackground = '#ede9fe';
                                                $requesterAvatarBorder = '#c4b5fd';
                                                $requesterAvatarText = '#4c1d95';
                                                if ($requesterGender === 'male') {
                                                    $requesterAvatarBackground = '#dbeafe';
                                                    $requesterAvatarBorder = '#93c5fd';
                                                    $requesterAvatarText = '#1e3a8a';
                                                } elseif ($requesterGender === 'female') {
                                                    $requesterAvatarBackground = '#fce7f3';
                                                    $requesterAvatarBorder = '#f9a8d4';
                                                    $requesterAvatarText = '#831843';
                                                }
                                            @endphp
                                            <div class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border text-[11px] font-semibold" style="background-color: {{ $requesterAvatarBackground }}; border-color: {{ $requesterAvatarBorder }}; color: {{ $requesterAvatarText }};">
                                                {{ $requesterInitials }}
                                            </div>
                                        @endif
                                        <span>{{ $request->requester?->name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="font-medium text-slate-800">{{ strtoupper($request->currency) }} {{ number_format((int) $request->amount) }}</p>
                                    <p class="text-xs text-slate-500">{{ $request->items_count }} item(s)</p>
                                    @if (($request->purchase_orders_count ?? 0) > 0)
                                        <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">PO Created</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $request->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($request->current_approval_step)
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                            Stage {{ $request->current_approval_step }}
                                        </span>
                                        @if (! empty($rowApprovalContexts[$request->id]['text'] ?? null))
                                            <p class="mt-1 text-[11px] text-slate-500">{{ $rowApprovalContexts[$request->id]['text'] }}</p>
                                        @endif
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                            Complete
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="openViewModal({{ $request->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openViewModal({{ $request->id }})"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="openViewModal({{ $request->id }})" class="inline-flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <span>View</span>
                                            </span>
                                            <span wire:loading wire:target="openViewModal({{ $request->id }})">Opening...</span>
                                        </button>
                                        @can('update', $request)
                                            <button
                                                type="button"
                                                wire:click="openEditModal({{ $request->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="openEditModal({{ $request->id }})"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="openEditModal({{ $request->id }})" class="inline-flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                                    </svg>
                                                    <span>Edit</span>
                                                </span>
                                                <span wire:loading wire:target="openEditModal({{ $request->id }})">Opening...</span>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    @if ($scopeFilter === 'pending_my_approval')
                                        No requests waiting for your action right now. You're all caught up.
                                    @elseif ($scopeFilter === 'decided_by_me')
                                        You have not decided any requests yet.
                                    @elseif ($scopeFilter === 'mine')
                                        You do not have requests matching this filter.
                                    @else
                                        No requests match the selected filters. Try adjusting the status, type, or date range, or clear all filters to see everything.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        Showing {{ $requests->firstItem() ?? 0 }}-{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }}
                    </p>
                    {{ $requests->links() }}
                </div>
            </div>
        @endif
    </div>

    @if ($showFormModal)
        <div wire:click="closeFormModal" class="fixed left-0 right-0 bottom-0 top-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full {{ $showFlowAgentsPanel && $flowAgentsContext === 'draft' ? 'max-w-6xl' : 'max-w-4xl' }} p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                                Request Draft
                            </span>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $isEditing ? 'Edit Request Draft' : 'Create Request Draft' }}</h2>
                            <p class="text-sm text-slate-500">Capture request details, items, and workflow before submission.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($flowAgentsEnabled)
                                <button type="button" wire:click="runFlowAgentsForDraft" wire:loading.attr="disabled" wire:target="runFlowAgentsForDraft" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 3v3"></path>
                                        <path d="M12 18v3"></path>
                                        <path d="M3 12h3"></path>
                                        <path d="M18 12h3"></path>
                                        <path d="M6.3 6.3l2.1 2.1"></path>
                                        <path d="M15.6 15.6l2.1 2.1"></path>
                                        <path d="M17.7 6.3l-2.1 2.1"></path>
                                        <path d="M8.4 15.6l-2.1 2.1"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="runFlowAgentsForDraft">Use Flow Agent</span>
                                    <span wire:loading wire:target="runFlowAgentsForDraft">Analyzing...</span>
                                </button>
                            @endif
                            <button type="button" wire:click="closeFormModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="{{ $showFlowAgentsPanel && $flowAgentsContext === 'draft' ? 'grid gap-4 xl:grid-cols-3' : '' }}">
                    <div class="{{ $showFlowAgentsPanel && $flowAgentsContext === 'draft' ? 'xl:col-span-2' : '' }}">
                    <form wire:submit.prevent="saveDraft" class="space-y-4">
                        @error('form.no_changes')
                            <div class="rounded-xl px-4 py-3 text-sm" style="background:#fffbeb;border:1px solid #f59e0b;color:#92400e;">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em]" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
                                    No Changes
                                </span>
                                <p class="mt-2">{{ $message }}</p>
                            </div>
                        @enderror

                        @php
                            $selectedType = $requestTypes->firstWhere('code', (string) ($form['type'] ?? ''));
                            $requiresLineItems = (bool) ($selectedType?->requires_line_items ?? false);
                            $requiresAmount = (bool) ($selectedType?->requires_amount ?? false);
                            $requiresDateRange = (bool) ($selectedType?->requires_date_range ?? false);
                            $requiresVendor = (bool) ($selectedType?->requires_vendor ?? false);
                        @endphp

                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Type</span>
                                <select wire:model.live="form.type" required class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select request type</option>
                                    @foreach ($requestTypes as $type)
                                        <option value="{{ $type->code }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Department</span>
                                <div class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                    {{ $currentUserDepartmentName }}
                                </div>
                                <input type="hidden" wire:model.defer="form.department_id">
                                @error('form.department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Currency (Company Base)</span>
                                <div class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium uppercase text-slate-700">
                                    {{ strtoupper($form['currency'] ?: 'NGN') }}
                                </div>
                                <input type="hidden" wire:model.defer="form.currency">
                                @error('form.currency')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Title</span>
                                <input type="text" wire:model.defer="form.title" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="What needs approval?">
                                @error('form.title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            @if ($requiresVendor || $requiresLineItems)
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Request-level Vendor {{ $requiresVendor ? '' : '(Optional)' }}</span>
                                    <select wire:model.defer="form.vendor_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                        <option value="">No vendor</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.vendor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            @endif

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Workflow</span>
                                <select wire:model.defer="form.workflow_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select workflow</option>
                                    @foreach ($workflows as $workflow)
                                        <option value="{{ $workflow->id }}">{{ $workflow->name }}{{ $workflow->is_default ? ' (Default)' : '' }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Choose the approval chain for this request.</p>
                                @error('form.workflow_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Needed By (Optional)</span>
                                <input type="date" wire:model.defer="form.needed_by" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.needed_by')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            @if ($requiresAmount && ! $requiresLineItems)
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Amount ({{ strtoupper($form['currency'] ?: 'NGN') }})</span>
                                    <input type="number" min="0" wire:model.defer="form.amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Enter amount">
                                    @error('form.amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            @endif

                            @if ($requiresDateRange)
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Start Date</span>
                                    <input type="date" wire:model.defer="form.start_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    @error('form.start_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">End Date</span>
                                    <input type="date" wire:model.defer="form.end_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    @error('form.end_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            @endif

                            @if ((string) ($form['type'] ?? '') === 'travel')
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Destination (Optional)</span>
                                    <input type="text" wire:model.defer="form.destination" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="City / Country">
                                    @error('form.destination')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            @endif

                            @if ((string) ($form['type'] ?? '') === 'leave')
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Leave Type (Optional)</span>
                                    <input type="text" wire:model.defer="form.leave_type" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Annual leave, Sick leave">
                                    @error('form.leave_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Handover To (Optional)</span>
                                    <select wire:model.defer="form.handover_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                        <option value="">No handover user</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.handover_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            @endif

                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Description (Optional)</span>
                                <textarea wire:model.defer="form.description" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                                @error('form.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        @if ($requiresLineItems)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-800">Line Items</p>
                                <button
                                    type="button"
                                    wire:click="addLineItem"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    Add Item
                                </button>
                            </div>

                            <div class="space-y-3">
                                @foreach ($lineItems as $index => $item)
                                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="grid gap-3 sm:grid-cols-6">
                                            <label class="block sm:col-span-2">
                                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Item</span>
                                                <input type="text" wire:model.defer="lineItems.{{ $index }}.name" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                                @error('lineItems.'.$index.'.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </label>

                                            <label class="block">
                                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Qty</span>
                                                <input type="number" min="1" wire:model.defer="lineItems.{{ $index }}.quantity" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                                @error('lineItems.'.$index.'.quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </label>

                                            <label class="block">
                                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Unit Cost</span>
                                                <input type="number" min="1" wire:model.defer="lineItems.{{ $index }}.unit_cost" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                                @error('lineItems.'.$index.'.unit_cost')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </label>

                                            <label class="block">
                                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Vendor</span>
                                                <select wire:model.defer="lineItems.{{ $index }}.vendor_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                                    <option value="">-</option>
                                                    @foreach ($vendors as $vendor)
                                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('lineItems.'.$index.'.vendor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </label>

                                            <label class="block">
                                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"> Category</span>
                                                <select wire:model.defer="lineItems.{{ $index }}.category" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                                    <option value="">Select category</option>
                                                    @foreach ($spendCategories as $category)
                                                        <option value="{{ $category->code }}">{{ $category->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('lineItems.'.$index.'.category')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </label>
                                        </div>

                                        <label class="mt-3 block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Description (Optional)</span>
                                            <textarea wire:model.defer="lineItems.{{ $index }}.description" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                                            @error('lineItems.'.$index.'.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </label>

                                        <div class="mt-3 flex items-center justify-between">
                                            <p class="text-xs text-slate-500">
                                                Line total:
                                                <span class="font-semibold text-slate-700">
                                                    {{ strtoupper($form['currency'] ?: 'NGN') }}
                                                    {{ number_format(((int) ($item['quantity'] ?: 0)) * ((int) ($item['unit_cost'] ?: 0))) }}
                                                </span>
                                            </p>
                                            @if (count($lineItems) > 1)
                                                <button type="button" wire:click="removeLineItem({{ $index }})" class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">
                                                    Remove
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if ($requiresLineItems)
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <span class="text-slate-500">Draft total:</span>
                            <span class="ml-2 font-semibold text-slate-900">
                                {{ strtoupper($form['currency'] ?: 'NGN') }}
                                {{ number_format(collect($lineItems)->sum(fn ($line) => ((int) ($line['quantity'] ?: 0)) * ((int) ($line['unit_cost'] ?: 0)))) }}
                            </span>
                        </div>
                        @endif

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21.44 11.05l-8.49 8.49a6 6 0 1 1-8.49-8.49l8.49-8.48a4 4 0 0 1 5.66 5.65l-8.48 8.49a2 2 0 0 1-2.83-2.83l7.78-7.78"></path>
                                    </svg>
                                    Attachments
                                </p>
                                <span class="text-xs text-slate-500">PDF, JPG, PNG, WEBP (max 10MB each)</span>
                            </div>

                            <label class="block">
                                <input
                                    type="file"
                                    wire:model="newAttachments"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    class="w-full rounded-xl border-slate-300 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 focus:border-slate-500 focus:ring-slate-500"
                                >
                            </label>
                            <div wire:loading wire:target="newAttachments" class="mt-2 text-xs font-medium text-slate-600">
                                Uploading...
                            </div>
                            @error('newAttachments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @foreach ($errors->get('newAttachments.*') as $messages)
                                @foreach ($messages as $message)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @endforeach
                            @endforeach

                            @if (! empty($newAttachments))
                                <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Selected Files</p>
                                    <div class="mt-2 space-y-1">
                                        @foreach ($newAttachments as $file)
                                            @if ($file)
                                                <p class="text-xs text-slate-700">{{ $file->getClientOriginalName() }}</p>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closeFormModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveDraft" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveDraft">{{ $isEditing ? 'Update Draft' : 'Save Draft' }}</span>
                                <span wire:loading wire:target="saveDraft">Saving...</span>
                            </button>
                        </div>
                    </form>
                    </div>
                    @if ($flowAgentsEnabled && $showFlowAgentsPanel && $flowAgentsContext === 'draft')
                        <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4 xl:sticky xl:top-2 xl:self-start">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Flow Agents</p>
                                    <p class="mt-1 text-xs text-slate-500">Draft guidance for policy readiness and approval quality.</p>
                                </div>
                                <button type="button" wire:click="closeFlowAgentsPanel" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-white">
                                    Hide
                                </button>
                            </div>

                            @if ($flowAgentsAdvisoryOnly)
                                <p class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[11px] text-indigo-700">
                                    Advisory only: Flow Agents does not auto-submit or override policy.
                                </p>
                            @endif

                            <div class="mt-3 flex items-center gap-2">
                                <button type="button" wire:click="runFlowAgentsForDraft" wire:loading.attr="disabled" wire:target="runFlowAgentsForDraft" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-70">
                                    <span wire:loading.remove wire:target="runFlowAgentsForDraft">Refresh</span>
                                    <span wire:loading wire:target="runFlowAgentsForDraft">Running...</span>
                                </button>
                                @if ($flowAgentsGeneratedAt)
                                    <span class="text-[11px] text-slate-500">Updated {{ $flowAgentsGeneratedAt }}</span>
                                @endif
                            </div>

                            @if ($flowAgentsSummary !== '')
                                <p class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">{{ $flowAgentsSummary }}</p>
                            @endif

                            <div class="mt-3 space-y-2">
                                @forelse ($flowAgentsItems as $item)
                                    @php
                                        $itemClass = 'border-emerald-200 bg-emerald-50 text-emerald-800';
                                        if (($item['severity'] ?? '') === 'action') {
                                            $itemClass = 'border-red-200 bg-red-50 text-red-800';
                                        } elseif (($item['severity'] ?? '') === 'watch') {
                                            $itemClass = 'border-amber-200 bg-amber-50 text-amber-800';
                                        }
                                    @endphp
                                    <div class="rounded-lg border px-3 py-2 {{ $itemClass }}">
                                        <p class="text-xs font-semibold">{{ $item['title'] ?? 'Flow Agent Suggestion' }}</p>
                                        <p class="mt-1 text-xs">{{ $item['message'] ?? '' }}</p>
                                        @if (! empty($item['action_key']) && $flowAgentsContext === 'view')
                                            <div class="mt-2">
                                                <button
                                                    type="button"
                                                    wire:click="runFlowAgentAction('{{ $item['action_key'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="runFlowAgentAction"
                                                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-70"
                                                >
                                                    <span wire:loading.remove wire:target="runFlowAgentAction">{{ $item['action_label'] ?? 'Run With Flow Agent' }}</span>
                                                    <span wire:loading wire:target="runFlowAgentAction">Running...</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500">No flow agent suggestions available yet.</p>
                                @endforelse
                            </div>
                        </aside>
                    @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showViewModal && $selectedRequest)
        <div wire:click="closeViewModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full {{ $showFlowAgentsPanel && $flowAgentsContext === 'view' ? 'max-w-6xl' : 'max-w-4xl' }} rounded-2xl border border-slate-200 bg-white shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                                Request Details
                            </span>
                            <h3 class="mt-1 text-xl font-semibold text-slate-900">{{ $selectedRequest['title'] }}</h3>
                            <p class="text-sm text-slate-500">{{ $selectedRequest['request_code'] }} &middot; {{ $selectedRequest['request_type_name'] }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($flowAgentsEnabled)
                                <button type="button" wire:click="runFlowAgentsForSelectedRequest" wire:loading.attr="disabled" wire:target="runFlowAgentsForSelectedRequest" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 3v3"></path>
                                        <path d="M12 18v3"></path>
                                        <path d="M3 12h3"></path>
                                        <path d="M18 12h3"></path>
                                        <path d="M6.3 6.3l2.1 2.1"></path>
                                        <path d="M15.6 15.6l2.1 2.1"></path>
                                        <path d="M17.7 6.3l-2.1 2.1"></path>
                                        <path d="M8.4 15.6l-2.1 2.1"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="runFlowAgentsForSelectedRequest">Use Flow Agent</span>
                                    <span wire:loading wire:target="runFlowAgentsForSelectedRequest">Analyzing...</span>
                                </button>
                            @endif
                            <button type="button" wire:click="closeViewModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="{{ $showFlowAgentsPanel && $flowAgentsContext === 'view' ? 'grid gap-4 xl:grid-cols-3' : '' }}">
                        <div class="space-y-4 {{ $showFlowAgentsPanel && $flowAgentsContext === 'view' ? 'xl:col-span-2' : '' }}">
                        <div class="grid gap-3 sm:grid-cols-3">
                            @php
                                $statusClass = 'bg-slate-100 text-slate-700';
                                if (in_array((string) ($selectedRequest['status'] ?? ''), ['approved', 'settled'], true)) {
                                    $statusClass = 'bg-emerald-100 text-emerald-700';
                                } elseif (in_array((string) ($selectedRequest['status'] ?? ''), ['rejected', 'failed', 'reversed'], true)) {
                                    $statusClass = 'bg-red-100 text-red-700';
                                } elseif (($selectedRequest['status'] ?? '') === 'in_review') {
                                    $statusClass = 'bg-amber-100 text-amber-700';
                                } elseif (($selectedRequest['status'] ?? '') === 'returned') {
                                    $statusClass = 'bg-indigo-100 text-indigo-700';
                                } elseif (($selectedRequest['status'] ?? '') === 'approved_for_execution') {
                                    $statusClass = 'bg-cyan-100 text-cyan-700';
                                } elseif (($selectedRequest['status'] ?? '') === 'execution_queued') {
                                    $statusClass = 'bg-sky-100 text-sky-700';
                                } elseif (($selectedRequest['status'] ?? '') === 'execution_processing') {
                                    $statusClass = 'bg-violet-100 text-violet-700';
                                }
                            @endphp
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Amount</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $selectedRequest['currency'] }} {{ number_format((int) $selectedRequest['amount']) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Status</p>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $selectedRequest['status'])) }}
                                </span>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Current Step</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ $selectedRequest['current_step_label'] ?: 'Process complete' }}
                                </p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Requester</dt>
                                    <dd class="mt-1">
                                        <div class="inline-flex items-center gap-2">
                                            @if (! empty($selectedRequest['requester_profile']['avatar_url']))
                                                <div class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                    <img
                                                        src="{{ $selectedRequest['requester_profile']['avatar_url'] }}"
                                                        alt="{{ $selectedRequest['requester_profile']['name'] }}"
                                                        class="h-full w-full object-cover"
                                                        style="object-position: center 30%;"
                                                        loading="lazy"
                                                    >
                                                </div>
                                            @else
                                                <div class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border text-[11px] font-semibold" style="background-color: {{ $selectedRequest['requester_profile']['avatar_bg'] ?? '#ede9fe' }}; border-color: {{ $selectedRequest['requester_profile']['avatar_border'] ?? '#c4b5fd' }}; color: {{ $selectedRequest['requester_profile']['avatar_text'] ?? '#4c1d95' }};">
                                                    {{ $selectedRequest['requester_profile']['initials'] ?? '?' }}
                                                </div>
                                            @endif
                                            <span class="font-medium text-slate-800">{{ $selectedRequest['requester'] }}</span>
                                        </div>
                                    </dd>
                                </div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Department</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['department'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Vendor</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['vendor'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Workflow</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['workflow'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Needed By</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['needed_by'] ?: '-' }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Start Date</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['start_date'] ?: '-' }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">End Date</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['end_date'] ?: '-' }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Destination</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['destination'] ?: '-' }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Leave Type</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['leave_type'] ?: '-' }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Submission Channels</dt><dd class="mt-1 font-medium text-slate-800">{{ !empty($selectedRequest['notification_channels']) ? implode(', ', $selectedRequest['notification_channels']) : 'Workflow default' }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Submitted At</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedRequest['submitted_at'] ?: '-' }}</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Description</p>
                            <p class="mt-2 text-sm text-slate-800">{{ $selectedRequest['description'] }}</p>
                        </div>

                        @if (! empty($selectedRequest['policy_warnings']))
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-800">Policy Warnings</p>
                                <div class="mt-2 space-y-1.5">
                                    @foreach ($selectedRequest['policy_warnings'] as $warning)
                                        <p class="text-xs text-amber-800">{{ $warning }}</p>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (count($selectedRequest['items']) > 0)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-800">Line Items</p>
                                <span class="text-xs text-slate-500">{{ count($selectedRequest['items']) }} item(s)</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="text-xs uppercase tracking-[0.12em] text-slate-500">
                                        <tr>
                                            <th class="py-2 text-left font-semibold">Item</th>
                                            <th class="py-2 text-left font-semibold">Qty</th>
                                            <th class="py-2 text-left font-semibold">Unit</th>
                                            <th class="py-2 text-left font-semibold">Total</th>
                                            <th class="py-2 text-left font-semibold">Vendor</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($selectedRequest['items'] as $item)
                                            <tr>
                                                <td class="py-2">
                                                    <p class="font-medium text-slate-800">{{ $item['name'] }}</p>
                                                    <p class="text-xs text-slate-500">{{ $item['category'] }}</p>
                                                </td>
                                                <td class="py-2 text-slate-700">{{ $item['quantity'] }}</td>
                                                <td class="py-2 text-slate-700">{{ $selectedRequest['currency'] }} {{ number_format((int) $item['unit_cost']) }}</td>
                                                <td class="py-2 font-medium text-slate-800">{{ $selectedRequest['currency'] }} {{ number_format((int) $item['line_total']) }}</td>
                                                <td class="py-2 text-slate-700">{{ $item['vendor'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21.44 11.05l-8.49 8.49a6 6 0 1 1-8.49-8.49l8.49-8.48a4 4 0 0 1 5.66 5.65l-8.48 8.49a2 2 0 0 1-2.83-2.83l7.78-7.78"></path>
                                    </svg>
                                    Attachments
                                </p>
                                <span class="text-xs text-slate-500">{{ count($selectedRequest['attachments']) }} file(s)</span>
                            </div>

                            <div class="space-y-2">
                                @forelse ($selectedRequest['attachments'] as $attachment)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800">{{ $attachment['original_name'] }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $attachment['mime_type'] }} &middot; {{ $attachment['file_size_kb'] }} KB &middot; Uploaded by {{ $attachment['uploaded_by'] }} @if($attachment['uploaded_at']) on {{ $attachment['uploaded_at'] }} @endif
                                            </p>
                                        </div>
                                        <a
                                            href="{{ $this->requestAttachmentDownloadUrlById((int) $attachment['id']) }}"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M10 2a1 1 0 011 1v7.586l2.293-2.293a1 1 0 111.414 1.414l-4.007 4.007a1 1 0 01-1.414 0L5.279 9.707a1 1 0 111.414-1.414L9 10.586V3a1 1 0 011-1z"></path>
                                                <path d="M4 14a1 1 0 011 1v1h10v-1a1 1 0 112 0v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2a1 1 0 011-1z"></path>
                                            </svg>
                                            Download
                                        </a>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No attachments uploaded yet.</p>
                                @endforelse
                            </div>

                            @if ($selectedRequest['can_upload_attachments'] && in_array($selectedRequest['status'], ['draft', 'returned'], true))
                                <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Upload More Files</p>
                                    <label class="mt-2 block">
                                        <input
                                            type="file"
                                            wire:model="viewNewAttachments"
                                            multiple
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            class="w-full rounded-xl border-slate-300 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 focus:border-slate-500 focus:ring-slate-500"
                                        >
                                    </label>
                                    <div wire:loading wire:target="viewNewAttachments" class="mt-2 text-xs font-medium text-slate-600">
                                        Uploading...
                                    </div>
                                    @error('viewNewAttachments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    @foreach ($errors->get('viewNewAttachments.*') as $messages)
                                        @foreach ($messages as $message)
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @endforeach
                                    @endforeach

                                    @if (! empty($viewNewAttachments))
                                        <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Selected Files</p>
                                            <div class="mt-1 space-y-1">
                                                @foreach ($viewNewAttachments as $file)
                                                    @if ($file)
                                                        <p class="text-xs text-slate-700">{{ $file->getClientOriginalName() }}</p>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="mt-2 flex justify-end">
                                        <button
                                            type="button"
                                            wire:click="uploadSelectedRequestAttachments"
                                            wire:loading.attr="disabled"
                                            wire:target="uploadSelectedRequestAttachments"
                                            class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="uploadSelectedRequestAttachments">Upload Attachments</span>
                                            <span wire:loading wire:target="uploadSelectedRequestAttachments">Uploading...</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-800">Approval Timeline</p>
                                @if (! empty($selectedRequest['current_approver_profiles']))
                                    <div class="flex flex-wrap items-center justify-end gap-1.5 text-xs text-slate-500">
                                        <span>Current approver(s):</span>
                                        @foreach ($selectedRequest['current_approver_profiles'] as $approverProfile)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2 py-1">
                                                @if (! empty($approverProfile['avatar_url']))
                                                    <span class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                        <img src="{{ $approverProfile['avatar_url'] }}" alt="{{ $approverProfile['name'] }}" class="h-full w-full object-cover" style="object-position: center 30%;" loading="lazy">
                                                    </span>
                                                @else
                                                    <span class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded-full border text-[10px] font-semibold" style="background-color: {{ $approverProfile['avatar_bg'] ?? '#ede9fe' }}; border-color: {{ $approverProfile['avatar_border'] ?? '#c4b5fd' }}; color: {{ $approverProfile['avatar_text'] ?? '#4c1d95' }};">
                                                        {{ $approverProfile['initials'] ?? '?' }}
                                                    </span>
                                                @endif
                                                <span class="font-medium text-slate-700">{{ $approverProfile['name'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif (! empty($selectedRequest['current_approvers']))
                                    <p class="text-xs text-slate-500">Current approver(s): {{ implode(', ', $selectedRequest['current_approvers']) }}</p>
                                @endif
                            </div>
                            <div class="space-y-2">
                                @forelse ($selectedRequest['timeline'] as $step)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        @php
                                            $timelineStatusClass = 'bg-slate-100 text-slate-700';
                                            if (($step['status'] ?? '') === 'approved') {
                                                $timelineStatusClass = 'bg-emerald-100 text-emerald-700';
                                            } elseif (($step['status'] ?? '') === 'rejected') {
                                                $timelineStatusClass = 'bg-red-100 text-red-700';
                                            } elseif (($step['status'] ?? '') === 'returned') {
                                                $timelineStatusClass = 'bg-indigo-100 text-indigo-700';
                                            } elseif (($step['status'] ?? '') === 'pending') {
                                                $timelineStatusClass = 'bg-amber-100 text-amber-700';
                                            } elseif (($step['status'] ?? '') === 'queued') {
                                                $timelineStatusClass = 'bg-sky-100 text-sky-700';
                                            }
                                        @endphp
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-700">
                                                    {{ $step['scope_label'] ?? 'Request Approval' }}
                                                </span>
                                                <p class="text-sm font-medium text-slate-800">{{ $step['step_label'] }}</p>
                                            </div>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $timelineStatusClass }}">
                                                {{ $step['status_label'] }}
                                            </span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                            <span>Outcome: {{ $step['decision'] }}</span>
                                            <span>&middot;</span>
                                            <span>Handled by:</span>
                                            @if (! empty($step['approver_profile']))
                                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5">
                                                    @if (! empty($step['approver_profile']['avatar_url']))
                                                        <span class="inline-flex h-4 w-4 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                            <img src="{{ $step['approver_profile']['avatar_url'] }}" alt="{{ $step['approver_profile']['name'] }}" class="h-full w-full object-cover" style="object-position: center 30%;" loading="lazy">
                                                        </span>
                                                    @else
                                                        <span class="inline-flex h-4 w-4 items-center justify-center overflow-hidden rounded-full border text-[9px] font-semibold" style="background-color: {{ $step['approver_profile']['avatar_bg'] ?? '#ede9fe' }}; border-color: {{ $step['approver_profile']['avatar_border'] ?? '#c4b5fd' }}; color: {{ $step['approver_profile']['avatar_text'] ?? '#4c1d95' }};">
                                                            {{ $step['approver_profile']['initials'] ?? '?' }}
                                                        </span>
                                                    @endif
                                                    <span class="text-slate-700">{{ $step['approver_profile']['name'] }}</span>
                                                </span>
                                            @else
                                                <span>{{ $step['approver'] }}</span>
                                            @endif
                                            <span>&middot;</span>
                                            <span>Action time: {{ $step['acted_at'] }}</span>
                                            @if (! empty($step['due_at']) && ($step['status'] ?? '') === 'pending')
                                                <span>&middot;</span>
                                                <span class="{{ ! empty($step['is_overdue']) ? 'font-semibold text-red-700' : 'text-slate-500' }}">
                                                    Due: {{ $step['due_at'] }}
                                                </span>
                                            @endif
                                            @if (! empty($step['reminder_sent_at']) && ($step['status'] ?? '') === 'pending')
                                                <span>&middot;</span>
                                                <span class="text-indigo-700">Reminder sent: {{ $step['reminder_sent_at'] }}</span>
                                            @endif
                                            @if (! empty($step['escalated_at']))
                                                <span>&middot;</span>
                                                <span class="font-semibold text-rose-700">Escalated: {{ $step['escalated_at'] }}</span>
                                            @endif
                                        </div>
                                        @if ($step['comment'])
                                            <p class="mt-1 text-xs text-slate-700">{{ $step['comment'] }}</p>
                                        @endif
                                        @if (! empty($step['delivery_summary']))
                                            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                                                <span class="text-slate-500">Delivery</span>
                                                @if (($step['delivery_summary']['sent'] ?? 0) > 0)
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700">Sent {{ (int) $step['delivery_summary']['sent'] }}</span>
                                                @endif
                                                @if (($step['delivery_summary']['queued'] ?? 0) > 0)
                                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700">Queued {{ (int) $step['delivery_summary']['queued'] }}</span>
                                                @endif
                                                @if (($step['delivery_summary']['failed'] ?? 0) > 0)
                                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 font-semibold text-red-700">Failed {{ (int) $step['delivery_summary']['failed'] }}</span>
                                                @endif
                                                @if (($step['delivery_summary']['skipped'] ?? 0) > 0)
                                                    <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 font-semibold text-indigo-700">Skipped {{ (int) $step['delivery_summary']['skipped'] }}</span>
                                                @endif
                                                @if (! empty($step['delivery_summary']['channels']))
                                                    <span class="text-slate-500">{{ implode(', ', (array) $step['delivery_summary']['channels']) }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No approval steps logged yet.</p>
                                @endforelse
                            </div>
                        </div>

                        @if ($selectedRequest['status'] === 'in_review' && $selectedRequest['can_approve'])
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold text-slate-800">Approval Action</p>
                                <label class="mt-2 block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Comment</span>
                                    <textarea wire:model.defer="decisionComment" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Required for reject/return"></textarea>
                                    @error('decisionComment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                                <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Approval Notification Channels</p>
                                    <p class="mt-1 text-xs text-slate-500">Select channels to notify next approver/requester after this action.</p>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                        @foreach ($decisionChannelPolicies as $channel => $policy)
                                            <label class="inline-flex items-center gap-2 text-xs {{ $policy['selectable'] ? 'text-slate-700' : 'text-slate-400' }}">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $channel }}"
                                                    wire:model.defer="decisionNotificationChannels"
                                                    @disabled(! $policy['selectable'])
                                                    class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                                                >
                                                @if ($channel === 'in_app')
                                                    <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <rect x="3" y="4" width="18" height="14" rx="2"></rect>
                                                        <path d="M8 21h8"></path>
                                                    </svg>
                                                @elseif ($channel === 'email')
                                                    <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                                        <path d="M3 7l9 6 9-6"></path>
                                                    </svg>
                                                @elseif ($channel === 'sms')
                                                    <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path>
                                                    </svg>
                                                @endif
                                                <span>
                                                    {{ $policy['label'] }}
                                                    @if (! $policy['selectable'])
                                                        ({{ ! $policy['enabled'] ? 'Disabled' : 'Not configured' }})
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('decisionNotificationChannels')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" wire:click="returnSelectedRequest" wire:loading.attr="disabled" wire:target="returnSelectedRequest" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-70">
                                        <span wire:loading.remove wire:target="returnSelectedRequest">Return</span>
                                        <span wire:loading wire:target="returnSelectedRequest">Processing...</span>
                                    </button>
                                    <button type="button" wire:click="rejectSelectedRequest" wire:loading.attr="disabled" wire:target="rejectSelectedRequest" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-70">
                                        <span wire:loading.remove wire:target="rejectSelectedRequest">Reject</span>
                                        <span wire:loading wire:target="rejectSelectedRequest">Processing...</span>
                                    </button>
                                    <button type="button" wire:click="approveSelectedRequest" wire:loading.attr="disabled" wire:target="approveSelectedRequest" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                        <span wire:loading.remove wire:target="approveSelectedRequest">Approve</span>
                                        <span wire:loading wire:target="approveSelectedRequest">Processing...</span>
                                    </button>
                                </div>
                            </div>
                        @elseif ($selectedRequest['status'] === 'in_review' && ! $selectedRequest['can_approve'])
                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                                <p class="text-sm font-semibold text-sky-800">Approval Context</p>
                                <p class="mt-1 text-xs text-sky-700">
                                    {{ $selectedRequest['approval_context_message'] ?: 'You can monitor this request. The current step is assigned to another approver.' }}
                                </p>
                            </div>
                        @endif

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">Expense Record</p>
                                @if ($selectedRequest['linked_expense'])
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Recorded</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">Not recorded yet</span>
                                @endif
                            </div>

                            @if ($selectedRequest['linked_expense'])
                                <p class="text-xs text-slate-600">
                                    {{ $selectedRequest['linked_expense']['expense_code'] }} &mdash;
                                    {{ $selectedRequest['linked_expense']['currency'] }} {{ number_format((int) $selectedRequest['linked_expense']['amount']) }} &mdash;
                                    {{ ucfirst((string) $selectedRequest['linked_expense']['status']) }}
                                    @if (! empty($selectedRequest['linked_expense']['expense_date']))
                                        &mdash; {{ $selectedRequest['linked_expense']['expense_date'] }}
                                    @endif
                                </p>
                            @else
                                <p class="text-xs text-slate-600">Log this approved request as an expense so it appears in your expense reports and spend history.</p>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">Purchase Order</p>
                                @if ($selectedRequest['linked_purchase_order'])
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Created</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Not created yet</span>
                                @endif
                            </div>

                            @if ($selectedRequest['linked_purchase_order'])
                                <p class="text-xs text-slate-600">
                                    <a href="{{ route('procurement.orders', ['search' => $selectedRequest['linked_purchase_order']['po_number']]) }}" class="font-semibold text-indigo-700 hover:underline">{{ $selectedRequest['linked_purchase_order']['po_number'] }}</a>
                                    &mdash;
                                    {{ $selectedRequest['linked_purchase_order']['currency'] }} {{ number_format((int) $selectedRequest['linked_purchase_order']['amount']) }} &mdash;
                                    {{ ucfirst(str_replace('_', ' ', (string) $selectedRequest['linked_purchase_order']['status'])) }}
                                </p>
                                <p class="mt-1.5 text-xs text-slate-500">Click the PO number above to open it in the Purchase Orders page.</p>
                            @else
                                <p class="text-xs text-slate-600">A Purchase Order is a formal document sent to the vendor that locks in what you are buying and at what price. It must be created before payment can be sent.</p>
                                @if (! empty($selectedRequest['convert_to_po_blocker']))
                                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-700">
                                        {{ $selectedRequest['convert_to_po_blocker'] }}
                                    </p>
                                @endif
                            @endif
                        </div>

                        @if (! empty($selectedRequest['mandatory_po_policy_message']))
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-800">Purchase Order Required</p>
                                <p class="mt-1 text-xs text-amber-700">{{ $selectedRequest['mandatory_po_policy_message'] }}</p>
                            </div>
                        @endif

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-800">Request Thread</p>
                                <span class="text-xs text-slate-500">{{ count($selectedRequest['comments']) }} message(s)</span>
                            </div>
                            <div class="max-h-64 space-y-1 overflow-y-auto pr-1">
                                @forelse ($selectedRequest['comments'] as $comment)
                                    @php
                                        $isMine = (bool) ($comment['is_mine'] ?? false);
                                    @endphp
                                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                                        <div class="max-w-[96%] rounded-xl border px-3.5 py-1 {{ $isMine ? 'border-slate-300 bg-slate-200 text-slate-800' : 'border-sky-200 bg-sky-50 text-slate-800' }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="inline-flex items-center gap-1.5">
                                                    @if (! empty($comment['author_profile']['avatar_url']))
                                                        <span class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                            <img src="{{ $comment['author_profile']['avatar_url'] }}" alt="{{ $comment['author_profile']['name'] }}" class="h-full w-full object-cover" style="object-position: center 30%;" loading="lazy">
                                                        </span>
                                                    @else
                                                        <span class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded-full border text-[10px] font-semibold" style="background-color: {{ $comment['author_profile']['avatar_bg'] ?? '#ede9fe' }}; border-color: {{ $comment['author_profile']['avatar_border'] ?? '#c4b5fd' }}; color: {{ $comment['author_profile']['avatar_text'] ?? '#4c1d95' }};">
                                                            {{ $comment['author_profile']['initials'] ?? '?' }}
                                                        </span>
                                                    @endif
                                                    <p class="text-[11px] font-semibold {{ $isMine ? 'text-slate-700' : 'text-sky-800' }}">{{ $isMine ? 'You' : $comment['author'] }}</p>
                                                </div>
                                                <span class="text-[10px] text-slate-500">{{ $comment['created_at'] }}</span>
                                            </div>
                                            <p class="mt-0.5 whitespace-pre-line text-sm leading-5 text-slate-700">{{ $comment['body'] }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No thread messages yet.</p>
                                @endforelse
                            </div>
                            @if ($selectedRequest['can_comment'])
                                <div class="mt-3">
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-slate-700">Add Message</span>
                                        <textarea wire:model.defer="threadComment" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Post a message to this request thread"></textarea>
                                        @error('threadComment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </label>
                                    <div class="mt-2 flex justify-end">
                                        <button type="button" wire:click="addThreadComment" wire:loading.attr="disabled" wire:target="addThreadComment" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70">
                                            <span wire:loading.remove wire:target="addThreadComment">Post Message</span>
                                            <span wire:loading wire:target="addThreadComment">Posting...</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-800">Communication History</p>
                                <span class="text-xs text-slate-500">{{ count($selectedRequest['communication_logs']) }} event(s)</span>
                            </div>
                            <div class="space-y-2">
                                @forelse ($selectedRequest['communication_logs'] as $log)
                                    @php
                                        $commStatusClass = 'bg-slate-100 text-slate-700';
                                        if (($log['status_key'] ?? '') === 'sent') {
                                            $commStatusClass = 'bg-emerald-100 text-emerald-700';
                                        } elseif (($log['status_key'] ?? '') === 'failed') {
                                            $commStatusClass = 'bg-red-100 text-red-700';
                                        } elseif (($log['status_key'] ?? '') === 'queued') {
                                            $commStatusClass = 'bg-amber-100 text-amber-700';
                                        } elseif (($log['status_key'] ?? '') === 'skipped') {
                                            $commStatusClass = 'bg-indigo-100 text-indigo-700';
                                        }
                                    @endphp
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-semibold text-slate-800">{{ $log['event'] }}</p>
                                            <span class="text-xs text-slate-500">{{ $log['created_at'] }}</span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                                            <span>Channel: {{ $log['channel'] }}</span>
                                            <span>&middot;</span>
                                            <span>Recipient:</span>
                                            <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5">
                                                @if (! empty($log['recipient_profile']['avatar_url']))
                                                    <span class="inline-flex h-4 w-4 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                        <img src="{{ $log['recipient_profile']['avatar_url'] }}" alt="{{ $log['recipient_profile']['name'] }}" class="h-full w-full object-cover" style="object-position: center 30%;" loading="lazy">
                                                    </span>
                                                @else
                                                    <span class="inline-flex h-4 w-4 items-center justify-center overflow-hidden rounded-full border text-[9px] font-semibold" style="background-color: {{ $log['recipient_profile']['avatar_bg'] ?? '#ede9fe' }}; border-color: {{ $log['recipient_profile']['avatar_border'] ?? '#c4b5fd' }}; color: {{ $log['recipient_profile']['avatar_text'] ?? '#4c1d95' }};">
                                                        {{ $log['recipient_profile']['initials'] ?? '?' }}
                                                    </span>
                                                @endif
                                                <span class="text-slate-700">{{ $log['recipient'] }}</span>
                                            </span>
                                            <span>&middot;</span>
                                            <span>Status:</span>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $commStatusClass }}">
                                                {{ $log['status'] }}
                                            </span>
                                            @if (($log['channel_key'] ?? '') === 'in_app' && ! empty($log['read_at']))
                                                <span>&middot;</span>
                                                <span class="text-emerald-700">Read: {{ $log['read_at'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No communication events logged yet.</p>
                                @endforelse
                            </div>
                        </div>

                        @if ($selectedRequest['can_submit'] && in_array($selectedRequest['status'], ['draft', 'returned'], true))
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold text-slate-800">Notification Channels</p>
                                <p class="mt-1 text-xs text-slate-500">Active workflow channels are preselected. Uncheck any channel you do not want for this submission.</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                    @foreach ($submitChannelPolicies as $channel => $policy)
                                        <label class="inline-flex items-center gap-2 text-xs {{ $policy['selectable'] ? 'text-slate-700' : 'text-slate-400' }}">
                                            <input
                                                type="checkbox"
                                                value="{{ $channel }}"
                                                wire:model.defer="submitNotificationChannels"
                                                @disabled(! $policy['selectable'])
                                                class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                                            >
                                            @if ($channel === 'in_app')
                                                <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <rect x="3" y="4" width="18" height="14" rx="2"></rect>
                                                    <path d="M8 21h8"></path>
                                                </svg>
                                            @elseif ($channel === 'email')
                                                <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                                    <path d="M3 7l9 6 9-6"></path>
                                                </svg>
                                            @elseif ($channel === 'sms')
                                                <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path>
                                                </svg>
                                            @endif
                                            <span>
                                                {{ $policy['label'] }}
                                                @if (! $policy['selectable'])
                                                    ({{ ! $policy['enabled'] ? 'Disabled' : 'Not configured' }})
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('submitNotificationChannels')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                @error('submitPolicy')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        </div>
                        @if ($flowAgentsEnabled && $showFlowAgentsPanel && $flowAgentsContext === 'view')
                            <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4 xl:sticky xl:top-2 xl:self-start">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Flow Agents</p>
                                        <p class="mt-1 text-xs text-slate-500">Live guidance for approval, handoff, and execution readiness.</p>
                                    </div>
                                    <button type="button" wire:click="closeFlowAgentsPanel" class="rounded-lg border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-white">
                                        Hide
                                    </button>
                                </div>

                                @if ($flowAgentsAdvisoryOnly)
                                    <p class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[11px] text-indigo-700">
                                        Advisory only: Flow Agents does not auto-approve or execute actions.
                                    </p>
                                @endif

                                <div class="mt-3 flex items-center gap-2">
                                    <button type="button" wire:click="runFlowAgentsForSelectedRequest" wire:loading.attr="disabled" wire:target="runFlowAgentsForSelectedRequest" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-70">
                                        <span wire:loading.remove wire:target="runFlowAgentsForSelectedRequest">Refresh</span>
                                        <span wire:loading wire:target="runFlowAgentsForSelectedRequest">Running...</span>
                                    </button>
                                    @if ($flowAgentsGeneratedAt)
                                        <span class="text-[11px] text-slate-500">Updated {{ $flowAgentsGeneratedAt }}</span>
                                    @endif
                                </div>

                                @if ($flowAgentsSummary !== '')
                                    <p class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">{{ $flowAgentsSummary }}</p>
                                @endif

                                <div class="mt-3 space-y-2">
                                    @forelse ($flowAgentsItems as $item)
                                        @php
                                            $itemClass = 'border-emerald-200 bg-emerald-50 text-emerald-800';
                                            if (($item['severity'] ?? '') === 'action') {
                                                $itemClass = 'border-red-200 bg-red-50 text-red-800';
                                            } elseif (($item['severity'] ?? '') === 'watch') {
                                                $itemClass = 'border-amber-200 bg-amber-50 text-amber-800';
                                            }
                                        @endphp
                                        <div class="rounded-lg border px-3 py-2 {{ $itemClass }}">
                                            <p class="text-xs font-semibold">{{ $item['title'] ?? 'Flow Agent Suggestion' }}</p>
                                            <p class="mt-1 text-xs">{{ $item['message'] ?? '' }}</p>
                                            @if (! empty($item['action_key']))
                                                <div class="mt-2">
                                                    <button
                                                        type="button"
                                                        wire:click="runFlowAgentAction('{{ $item['action_key'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="runFlowAgentAction"
                                                        class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-70"
                                                    >
                                                        <span wire:loading.remove wire:target="runFlowAgentAction">{{ $item['action_label'] ?? 'Run With Flow Agent' }}</span>
                                                        <span wire:loading wire:target="runFlowAgentAction">Running...</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500">No flow agent suggestions available yet.</p>
                                    @endforelse
                                </div>
                            </aside>
                        @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                        @if ($selectedRequest['can_update'])
                            <button type="button" wire:click="openEditModal({{ $selectedRequest['id'] }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                    </svg>
                                    <span>Edit Draft</span>
                                </span>
                            </button>
                        @endif
                        @if ($selectedRequest['can_convert_to_po'])
                            <button type="button" wire:click="convertSelectedRequestToPurchaseOrder" wire:loading.attr="disabled" wire:target="convertSelectedRequestToPurchaseOrder" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100 disabled:opacity-70">
                                <span wire:loading.remove wire:target="convertSelectedRequestToPurchaseOrder">Create Purchase Order</span>
                                <span wire:loading wire:target="convertSelectedRequestToPurchaseOrder">Creating...</span>
                            </button>
                        @elseif ($selectedRequest['linked_purchase_order'])
                            <a href="{{ route('procurement.orders', ['search' => $selectedRequest['linked_purchase_order']['po_number']]) }}" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100" title="Open this Purchase Order">
                                <span>Purchase Order Created</span>
                                <span class="rounded-full border border-emerald-200 bg-white px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                    {{ $selectedRequest['linked_purchase_order']['po_number'] }}
                                </span>
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="m12 5 7 7-7 7"></path>
                                </svg>
                            </a>
                        @else
                            <button type="button" disabled class="cursor-not-allowed rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-400">
                                Create Purchase Order
                            </button>
                        @endif
                        @if ($selectedRequest['can_create_expense'])
                            <button type="button" wire:click="createExpenseFromSelectedRequest" wire:loading.attr="disabled" wire:target="createExpenseFromSelectedRequest" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:opacity-70">
                                <span wire:loading.remove wire:target="createExpenseFromSelectedRequest">Create Expense</span>
                                <span wire:loading wire:target="createExpenseFromSelectedRequest">Creating...</span>
                            </button>
                        @endif
                        @if ($selectedRequest['can_submit'] && in_array($selectedRequest['status'], ['draft', 'returned'], true))
                            <button type="button" wire:click="submitSelectedRequest" wire:loading.attr="disabled" wire:target="submitSelectedRequest" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="submitSelectedRequest">Submit for Approval</span>
                                <span wire:loading wire:target="submitSelectedRequest">Submitting...</span>
                            </button>
                        @endif
                        <button type="button" wire:click="closeViewModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

