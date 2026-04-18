<div wire:init="loadData" class="space-y-6">
    <section class="fd-card border border-indigo-200 bg-indigo-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('operations.control-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">&larr; Back to Operations Overview</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Approvals</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Approvals Overview</h2>
                <p class="mt-1 text-sm text-slate-700">Pending approvals, overdue items, and returned requests — all in one place.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('operations.vendor-payables-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Vendor Payables<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('operations.period-close-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Month-End Close<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="approval-ops-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Approvals</label>
                <input id="approval-ops-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Request code, requester, title" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs text-slate-600 md:col-span-2">
                One next action per row keeps approvals direct and reduces page jumping.
            </div>
        </div>
    </section>

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-2 h-3 w-32 rounded bg-slate-200"></div>
                    <div class="h-7 w-20 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @elseif (! $approvalDesk['enabled'])
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $approvalDesk['disabled_reason'] }}
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Pending My Approval</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($approvalDesk['summary']['pending_count'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Overdue</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($approvalDesk['summary']['overdue_count'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Returned</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($approvalDesk['summary']['returned_count'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Total Workload</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($approvalDesk['summary']['workload_total'] ?? 0)) }}</p>
            </div>
        </section>

        <section class="fd-card border border-indigo-200 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Progress Overview</p>
            <p class="mt-1 text-sm text-slate-700">Current bottleneck: {{ $approvalDesk['summary']['bottleneck_label'] ?? 'No blockers' }} ({{ number_format((int) ($approvalDesk['summary']['bottleneck_count'] ?? 0)) }})</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
                <div class="flex h-full w-full">
                    @foreach (($approvalDesk['summary']['segments'] ?? []) as $segment)
                        @if ((int) ($segment['count'] ?? 0) > 0)
                            @php
                                $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                    'indigo' => 'bg-indigo-400',
                                    'rose' => 'bg-rose-400',
                                    'amber' => 'bg-amber-400',
                                    default => 'bg-slate-400',
                                };
                            @endphp
                            <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        <section class="fd-card border border-slate-200 bg-slate-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Pending Approvals (Need Decision)</h3>
                    <p class="text-xs text-slate-500">Requests currently waiting for approval action.</p>
                </div>
                <a href="{{ route('requests.index') }}" class="rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Open Requests</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Request</th>
                            <th class="px-3 py-2">Context</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Next Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($approvalDesk['lanes']['pending'] ?? []) as $row)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row['ref'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    <p>{{ $row['meta'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['context'] }}</p>
                                </td>
                                <td class="px-3 py-3"><span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-800">{{ $row['status'] }}</span></td>
                                <td class="px-3 py-3 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">{{ $row['next_action_label'] }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No pending approval actions for the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="fd-card border border-rose-200 bg-rose-50 p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Overdue Approvals</h3>
                        <p class="text-xs text-slate-500">Overdue approvals that should be handled first.</p>
                    </div>
                </div>
                <div class="space-y-2">
                    @forelse (($approvalDesk['lanes']['overdue'] ?? []) as $row)
                        <div class="rounded-xl border border-rose-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} · {{ $row['status'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['context'] }}</p>
                            <div class="mt-2 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">{{ $row['next_action_label'] }}</a></div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No overdue approvals right now.</p>
                    @endforelse
                </div>
            </div>

            <div class="fd-card border border-amber-200 bg-amber-50 p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Returned Requests (Need Update)</h3>
                        <p class="text-xs text-slate-500">Returned requests that need update/resubmission.</p>
                    </div>
                </div>
                <div class="space-y-2">
                    @forelse (($approvalDesk['lanes']['returned'] ?? []) as $row)
                        <div class="rounded-xl border border-amber-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} · {{ $row['status'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['context'] }}</p>
                            <div class="mt-2 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">{{ $row['next_action_label'] }}</a></div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No returned requests right now.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</div>
