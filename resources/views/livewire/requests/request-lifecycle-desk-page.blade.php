<div wire:init="loadData" class="space-y-6">
    <section class="fd-card border border-indigo-200 bg-indigo-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Request Tracker</h2>
                <p class="mt-1 text-sm text-slate-700">Track every approved request from start to payment — see exactly where each one is and what needs to happen next.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('requests.index') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Open Requests</a>
                @if (($canOpenPayoutQueue ?? false))
                    <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Payments Ready to Send</a>
                @endif
                <a href="{{ route('requests.lifecycle-help') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Request Tracker Guide</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <label for="request-lifecycle-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search</label>
                <input id="request-lifecycle-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Request code, title, requester" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs text-slate-600">
                Each row shows one clear next step — work through them top to bottom.
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
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">No Purchase Order Yet</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['approved_need_po'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Purchase Order in Progress</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['po_match_followup'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Ready to Pay</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['waiting_dispatch'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Payment In Progress</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['execution_active_retry'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Paid / Cancelled</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['closed_outcomes'] ?? 0)) }}</p></div>
        </section>

        <section class="fd-card border border-indigo-200 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Progress Overview</p>
            <p class="mt-1 text-sm text-slate-700">Biggest hold-up: {{ $desk['summary']['bottleneck_label'] ?? 'None' }} ({{ number_format((int) ($desk['summary']['bottleneck_count'] ?? 0)) }})</p>
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
                    <h3 class="text-sm font-semibold text-slate-900">Approved — Create a Purchase Order</h3>
                    <p class="text-xs text-slate-500">These requests are fully approved. Create a Purchase Order for each one to move forward.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['approved_need_po'] ?? []])
        </section>

        <section class="fd-card border border-indigo-200 bg-indigo-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Purchase Order — Action Needed</h3>
                    <p class="text-xs text-slate-500">A Purchase Order exists but something needs fixing before payment can be sent.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['po_match_followup'] ?? []])
        </section>

        <section class="fd-card border border-emerald-200 bg-emerald-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Ready to Send Payment</h3>
                    <p class="text-xs text-slate-500">Fully approved and ready. Send the payment from the payments queue.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['waiting_dispatch'] ?? []])
        </section>

        <section class="fd-card border border-rose-200 bg-rose-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Payment In Progress</h3>
                    <p class="text-xs text-slate-500">Payment is being processed or failed and needs to be retried.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['execution_active_retry'] ?? []])
        </section>

        <section class="fd-card border border-slate-200 bg-slate-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Paid & Closed</h3>
                    <p class="text-xs text-slate-500">Requests that have been paid or cancelled. No further action needed.</p>
                </div>
            </div>
            @include('livewire.requests.partials.request-lifecycle-lane-table', ['rows' => $desk['lanes']['closed_outcomes'] ?? []])
        </section>
    @endif
</div>

