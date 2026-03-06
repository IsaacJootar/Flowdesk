<div wire:init="loadData" class="space-y-6">
    <section class="fd-card border border-indigo-200 bg-indigo-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Requests Workspace</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Request Lifecycle Desk</h2>
                <p class="mt-1 text-sm text-slate-700">Single operator page from approved request to payout-ready execution and closure outcomes.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('requests.index') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Open Requests</a>
                @if (($canOpenPayoutQueue ?? false))
                    <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Open Payout Queue</a>
                @endif
                <a href="{{ route('requests.lifecycle-help') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Help / Usage Guide</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <label for="request-lifecycle-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Desk</label>
                <input id="request-lifecycle-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Request code, title, requester" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs text-slate-600">
                One next action per row keeps request operations direct and auditable.
            </div>
        </div>
    </section>

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @for ($i = 0; $i < 5; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-2 h-3 w-32 rounded bg-slate-200"></div>
                    <div class="h-7 w-20 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @elseif (! $desk['enabled'])
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $desk['disabled_reason'] }}
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Approved (Need PO)</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['approved_need_po'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">PO / Match Follow-up</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['po_match_followup'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Waiting Dispatch</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['waiting_dispatch'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Execution Active / Retry</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['execution_active_retry'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Settled / Reversed</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['closed_outcomes'] ?? 0)) }}</p></div>
        </section>

        <section class="fd-card border border-indigo-200 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Lifecycle Workload Progress</p>
            <p class="mt-1 text-sm text-slate-700">Current bottleneck: {{ $desk['summary']['bottleneck_label'] ?? 'No blockers' }} ({{ number_format((int) ($desk['summary']['bottleneck_count'] ?? 0)) }})</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
                <div class="flex h-full w-full">
                    @foreach (($desk['summary']['segments'] ?? []) as $segment)
                        @if ((int) ($segment['count'] ?? 0) > 0)
                            @php
                                $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                    'amber' => 'bg-amber-400',
                                    'indigo' => 'bg-indigo-400',
                                    'emerald' => 'bg-emerald-400',
                                    'rose' => 'bg-rose-400',
                                    default => 'bg-slate-400',
                                };
                            @endphp
                            <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        <section class="fd-card border border-amber-200 bg-amber-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Approved Requests (Need PO)</h3>
                    <p class="text-xs text-slate-500">Final approvals completed, but procurement conversion has not started.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['approved_need_po'] ?? []])
        </section>

        <section class="fd-card border border-indigo-200 bg-indigo-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">PO / Match Follow-up</h3>
                    <p class="text-xs text-slate-500">PO exists, but procurement controls still need action before payout dispatch.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['po_match_followup'] ?? []])
        </section>

        <section class="fd-card border border-emerald-200 bg-emerald-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Approved for Execution (Waiting Payout Dispatch)</h3>
                    <p class="text-xs text-slate-500">Ready to move money from the payout queue.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['waiting_dispatch'] ?? []])
        </section>

        <section class="fd-card border border-rose-200 bg-rose-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Execution Active / Retry</h3>
                    <p class="text-xs text-slate-500">Rows currently queued/processing or failed and waiting operator retry.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['execution_active_retry'] ?? []])
        </section>

        <section class="fd-card border border-slate-200 bg-slate-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Settled / Reversed Outcomes</h3>
                    <p class="text-xs text-slate-500">Recent requests that exited payout waiting queue.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['closed_outcomes'] ?? []])
        </section>
    @endif
</div>

