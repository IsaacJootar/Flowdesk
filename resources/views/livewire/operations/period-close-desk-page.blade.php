<div wire:init="loadData" class="space-y-6">
    <x-module-explainer
        key="period-close"
        title="Month-End Close"
        description="A guided checklist that walks your finance team through every step needed to close the current accounting period cleanly and confidently."
        :bullets="[
            'Each checklist item must be completed or deliberately skipped before the period can be closed.',
            'Outstanding requests, unmatched bank items, and unreconciled payables are flagged automatically.',
            'Once all items pass, you confirm close and the period is locked against further changes.',
        ]"
    />
    <section class="fd-card border border-rose-200 bg-rose-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('operations.control-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">&larr; Back to Operations Overview</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Month-End</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Month-End Close</h2>
                <p class="mt-1 text-sm text-slate-700">One page for month-end readiness checks across bank reconciliation, purchase orders, payment retries, and audit flags.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('operations.approval-desk') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Approvals Overview</a>
                <a href="{{ route('operations.vendor-payables-desk') }}" class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Vendor Payables</a>
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
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Unreconciled Lines</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($closeDesk['summary']['unreconciled_lines'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Purchase Order Issues</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($closeDesk['summary']['open_procurement_exceptions'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Failed Payments</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($closeDesk['summary']['failed_payouts'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Audit Flags (30d)</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($closeDesk['summary']['audit_flags'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Close Status</p><p class="mt-2 text-2xl font-semibold">{{ $closeDesk['summary']['close_status'] ?? 'Action Needed' }}</p></div>
        </section>

        <section class="fd-card border border-rose-200 bg-rose-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Close Readiness Progress</p>
            <p class="mt-1 text-sm text-slate-700">Current bottleneck: {{ $closeDesk['summary']['bottleneck_label'] ?? 'No blockers' }} ({{ number_format((int) ($closeDesk['summary']['bottleneck_count'] ?? 0)) }})</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="flex h-full w-full">
                @foreach (($closeDesk['summary']['segments'] ?? []) as $segment)
                    @if ((int) ($segment['count'] ?? 0) > 0)
                        @php
                            $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                'amber' => 'bg-amber-400',
                                'rose' => 'bg-rose-400',
                                'sky' => 'bg-sky-400',
                                'indigo' => 'bg-indigo-400',
                                default => 'bg-slate-400',
                            };
                        @endphp
                        <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                    @endif
                @endforeach
            </div></div>
        </section>

        <section class="fd-card border border-slate-200 bg-slate-50 p-4">
            <div class="mb-3">
                <h3 class="text-sm font-semibold text-slate-900">Month-End Close Checklist (One Action Per Row)</h3>
                <p class="text-xs text-slate-500">Use this desk before close signoff to remove unresolved operations blockers.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Check</th>
                            <th class="px-3 py-2">Count</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Notes</th>
                            <th class="px-3 py-2 text-right">Next Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($closeDesk['lanes']['checks'] ?? []) as $row)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $row['label'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ number_format((int) ($row['count'] ?? 0)) }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ (string) ($row['status'] ?? '') === 'Ready' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $row['status'] }}</span>
                                </td>
                                <td class="px-3 py-3 text-slate-600">{{ $row['note'] }}</td>
                                <td class="px-3 py-3 text-right">
                                    @if (! empty($row['next_action_url']) && ! empty($row['next_action_label']))
                                        <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ $row['next_action_label'] }}</a>
                                    @else
                                        <span class="text-xs text-slate-400">No action</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
