<div class="space-y-6">
    <x-module-explainer
        key="procurement-workspace"
        title="Purchase Order Workspace"
        description="The daily workspace for your procurement team — manage open orders, confirm goods received, match supplier invoices, and release payments."
        :bullets="[
            'Match a supplier invoice to a Purchase Order and confirm goods received before releasing payment.',
            'If the invoice price or quantity doesn\'t match the Purchase Order, payment is blocked until it\'s fixed.',
            'Once everything checks out and is approved, the payment is queued automatically.',
        ]"
        guide-route="procurement.release-help"
    />
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Purchase Order Workspace</h2>
                <p class="mt-1 text-sm text-slate-600">Move each order from approved request through to payment — all in one place.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('procurement.orders') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Orders</a>
                <a href="{{ route('procurement.receipts') }}" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Receipts</a>
                <a href="{{ route('procurement.match-exceptions') }}" class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Match Issues</a>
                <a href="{{ route('requests.lifecycle-desk') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Request Tracker</a>
                <a href="{{ route('procurement.release-help') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Help / Usage Guide</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="procurement-workspace-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Workspace</label>
                <input id="procurement-workspace-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Request code, Purchase Order number, title, vendor" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Each row shows one clear next step — work through them from top to bottom.
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">No Purchase Order Yet</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['approved_need_po'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-indigo-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Not Sent to Vendor</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['po_drafts_need_issue'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sky-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Waiting for Receipt</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['issued_need_receipt'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Invoice Mismatch</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['invoice_match_resolve'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Ready for Payout</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['ready_for_payout'] ?? 0)) }}</p>
        </div>
    </section>

    <section class="fd-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-indigo-700">Progress Overview</span>
                <p class="mt-2 text-xs font-semibold uppercase tracking-[0.12em] text-indigo-700">Focus on the biggest hold-up first</p>
                <p class="mt-1 text-sm text-slate-700">Open items: <span class="font-semibold">{{ number_format((int) ($summary['workload_total'] ?? 0)) }}</span></p>
                <p class="text-xs text-slate-500">Biggest hold-up: {{ $summary['bottleneck_label'] ?? 'None' }} ({{ number_format((int) ($summary['bottleneck_count'] ?? 0)) }})</p>
            </div>
            <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Payments Ready to Send</a>
        </div>

        <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
            <div class="flex h-full w-full">
                @foreach (($summary['segments'] ?? []) as $segment)
                    @if ((int) ($segment['count'] ?? 0) > 0)
                        @php
                            $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                'amber' => 'bg-amber-400',
                                'indigo' => 'bg-indigo-400',
                                'sky' => 'bg-sky-400',
                                'rose' => 'bg-rose-400',
                                default => 'bg-slate-400',
                            };
                        @endphp
                        <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
            @foreach (($summary['segments'] ?? []) as $segment)
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-1">
                    <span>{{ $segment['label'] ?? '-' }}</span>
                    <span class="font-semibold">{{ number_format((int) ($segment['count'] ?? 0)) }}</span>
                </span>
            @endforeach
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Approved Requests — Create a Purchase Order</h3>
                <p class="text-xs text-slate-500">These requests are approved. The next step is to create a Purchase Order for each one.</p>
            </div>
            <a href="{{ route('requests.index') }}" class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100">Open Requests</a>
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
                    @forelse ($approvedRequestsLane as $row)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $row['ref'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $row['meta'] }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $row['status'] }}</span></td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">{{ $row['next_action_label'] }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No approved requests are waiting for a Purchase Order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Purchase Orders — Not Yet Sent to Vendor</h3>
                <p class="text-xs text-slate-500">These Purchase Orders are saved as drafts. Send them to the vendor to move forward.</p>
            </div>
            <a href="{{ route('procurement.orders') }}" class="rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Open Orders</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Purchase Order</th>
                        <th class="px-3 py-2">Context</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($poDraftsLane as $row)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $row['ref'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $row['meta'] }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-800">{{ $row['status'] }}</span></td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">{{ $row['next_action_label'] }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No draft Purchase Orders are waiting to be sent to a vendor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Purchase Orders — Waiting for Delivery</h3>
                <p class="text-xs text-slate-500">Goods have been ordered but not confirmed as received yet. Confirm delivery to move forward.</p>
            </div>
            <a href="{{ route('procurement.orders') }}" class="rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100">Open Orders</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Purchase Order</th>
                        <th class="px-3 py-2">Context</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($issuedReceiptLane as $row)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $row['ref'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $row['meta'] }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ $row['status'] }}</span></td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">{{ $row['next_action_label'] }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No orders are waiting for delivery confirmation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Invoices — Mismatch to Fix</h3>
                <p class="text-xs text-slate-500">These orders have an invoice that doesn't match the Purchase Order. Fix the mismatch to release payment.</p>
            </div>
            <a href="{{ route('procurement.match-exceptions') }}" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">Open Match Issues</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Purchase Order</th>
                        <th class="px-3 py-2">Context</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoiceResolveLane as $row)
                        @php
                            $statusTone = (string) ($row['status'] ?? '') === 'Invoice Not Attached'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-rose-200 bg-rose-50 text-rose-800';
                            $actionTone = (string) ($row['next_action_tone'] ?? '') === 'emerald'
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                : 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100';
                        @endphp
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $row['ref'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $row['meta'] }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $row['status'] }}</span></td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $actionTone }}">{{ $row['next_action_label'] }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No invoice mismatches to fix right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Ready to Pay</h3>
                <p class="text-xs text-slate-500">Everything checks out on these orders — invoice matched, goods received. Send the payment.</p>
            </div>
            <a href="{{ route('execution.payout-ready') }}" class="rounded-lg border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200">Payments Ready to Send</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Purchase Order</th>
                        <th class="px-3 py-2">Context</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($readyPayoutLane as $row)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $row['ref'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $row['meta'] }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $row['status'] }}</span></td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">{{ $row['next_action_label'] }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No orders are ready for payment yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

