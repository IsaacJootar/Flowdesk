<div wire:init="loadData" class="space-y-6">
    <x-module-explainer
        key="vendor-payables"
        title="What We Owe Vendors"
        description="A real-time view of all outstanding amounts owed to suppliers — approved but unpaid invoices, credit notes, and overdue balances."
        :bullets="[
            'Items are grouped by vendor so you can see total exposure per supplier at a glance.',
            'Overdue payables are highlighted in red — these should be the first to be queued for payment.',
            'Releasing a payable here queues it for the next payment run automatically.',
        ]"
    />
    <section class="fd-card border border-amber-200 bg-amber-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('operations.control-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-white px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100">&larr; Back to Operations Overview</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Desk 2</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">What We Owe Vendors Desk</h2>
                <p class="mt-1 text-sm text-slate-700">One execution page for invoices, part-pay balances, blocked handoffs, and payout retries.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('operations.approval-desk') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Approval Desk</a>
                <a href="{{ route('operations.period-close-desk') }}" class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Period Close Desk</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="payables-ops-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Desk</label>
                <input id="payables-ops-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Invoice number, vendor, request code" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs text-slate-600 md:col-span-2">
                One next action per row keeps payables movement direct and auditable.
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
    @elseif (! $payablesDesk['enabled'])
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $payablesDesk['disabled_reason'] }}
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Open Invoices</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($payablesDesk['summary']['open_invoice_count'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Part-Paid</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($payablesDesk['summary']['part_paid_count'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Blocked Handoff</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($payablesDesk['summary']['blocked_handoff_count'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Failed Retries</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($payablesDesk['summary']['failed_retry_count'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Total Workload</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($payablesDesk['summary']['workload_total'] ?? 0)) }}</p></div>
        </section>

        <section class="fd-card border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Payables Workload Progress</p>
            <p class="mt-1 text-sm text-slate-700">Current bottleneck: {{ $payablesDesk['summary']['bottleneck_label'] ?? 'No blockers' }} ({{ number_format((int) ($payablesDesk['summary']['bottleneck_count'] ?? 0)) }})</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="flex h-full w-full">
                @foreach (($payablesDesk['summary']['segments'] ?? []) as $segment)
                    @if ((int) ($segment['count'] ?? 0) > 0)
                        @php
                            $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                'indigo' => 'bg-indigo-400',
                                'amber' => 'bg-amber-400',
                                'rose' => 'bg-rose-400',
                                'sky' => 'bg-sky-400',
                                default => 'bg-slate-400',
                            };
                        @endphp
                        <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                    @endif
                @endforeach
            </div></div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="fd-card border border-indigo-200 bg-indigo-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Invoices Awaiting Payment</h3>
                <p class="mb-3 text-xs text-slate-500">Outstanding vendor invoices waiting for payment action.</p>
                <div class="space-y-2">
                    @forelse (($payablesDesk['lanes']['open_invoices'] ?? []) as $row)
                        <div class="rounded-xl border border-indigo-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} · {{ $row['status'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['meta'] }}</p>
                            <div class="mt-2 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">{{ $row['next_action_label'] }}</a></div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No open invoices in current filter.</p>
                    @endforelse
                </div>
            </div>
            <div class="fd-card border border-amber-200 bg-amber-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Part-Paid Invoices</h3>
                <p class="mb-3 text-xs text-slate-500">Invoices where additional payment is still required.</p>
                <div class="space-y-2">
                    @forelse (($payablesDesk['lanes']['part_paid'] ?? []) as $row)
                        <div class="rounded-xl border border-amber-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} · {{ $row['status'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['meta'] }}</p>
                            <div class="mt-2 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">{{ $row['next_action_label'] }}</a></div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No part-paid invoices in current filter.</p>
                    @endforelse
                </div>
            </div>
            <div class="fd-card border border-rose-200 bg-rose-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Blocked Payout Handoffs</h3>
                <p class="mb-3 text-xs text-slate-500">Requests blocked by procurement gate and still pending ops resolution.</p>
                <div class="space-y-2">
                    @forelse (($payablesDesk['lanes']['blocked_handoff'] ?? []) as $row)
                        <div class="rounded-xl border border-rose-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} · {{ $row['status'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['context'] }}</p>
                            <div class="mt-2 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">{{ $row['next_action_label'] }}</a></div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No blocked payout handoffs in current filter.</p>
                    @endforelse
                </div>
            </div>
            <div class="fd-card border border-sky-200 bg-sky-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Failed Payout Retries</h3>
                <p class="mb-3 text-xs text-slate-500">Failed payout attempts requiring rerun and validation.</p>
                <div class="space-y-2">
                    @forelse (($payablesDesk['lanes']['failed_retries'] ?? []) as $row)
                        <div class="rounded-xl border border-sky-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} · {{ $row['status'] }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['meta'] }}</p>
                            <div class="mt-2 text-right"><a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">{{ $row['next_action_label'] }}</a></div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No failed payout retries in current filter.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</div>
