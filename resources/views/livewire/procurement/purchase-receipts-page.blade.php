<div wire:init="loadData" class="space-y-5">
        <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Procurement Receipts</h2>
            <p class="text-xs text-slate-500">Review receipt history and linked vendor invoice coverage.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Purchase Order Workspace</span>
            </a>
            <a href="{{ route('procurement.release-help') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">
                Purchase Order Guide
            </a>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-5">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Receipt number, PO number, vendor">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Receipt Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Received From</span>
                <input type="date" wire:model.live="receivedFrom" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Received To</span>
                <input type="date" wire:model.live="receivedTo" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70">
            <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
            <span wire:loading wire:target="exportCsv">Exporting...</span>
        </button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Receipts</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $summary['total']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Confirmed</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $summary['confirmed']) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-indigo-700">Received Value</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">NGN {{ number_format((int) $summary['value']) }}</p>
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
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Receipt</th>
                            <th class="px-4 py-3 text-left font-semibold">Purchase Order</th>
                            <th class="px-4 py-3 text-left font-semibold">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold">Received At</th>
                            <th class="px-4 py-3 text-left font-semibold">Lines</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($receipts as $receipt)
                            @php
                                $statusClass = (string) $receipt->receipt_status === 'confirmed'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : ((string) $receipt->receipt_status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $receipt->receipt_number }}</p>
                                    <p class="text-xs text-slate-500">Receiver: {{ $receipt->receiver?->name ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ $receipt->order?->po_number ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', (string) ($receipt->order?->po_status ?? '-'))) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $receipt->order?->vendor?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($receipt->received_at)->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ (int) $receipt->items_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $receipt->receipt_status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="openDetails({{ $receipt->id }})" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            <span>View</span>
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No procurement receipts found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Showing {{ $receipts->firstItem() ?? 0 }}-{{ $receipts->lastItem() ?? 0 }} of {{ $receipts->total() }}</p>
                    {{ $receipts->links() }}
                </div>
            </div>
        @endif
    </div>

    @if ($showDetailsModal && $selectedReceipt)
        <div wire:click="closeDetails" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-4xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">Procurement Receipt</span>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $selectedReceipt['receipt_number'] }}</h2>
                            <p class="text-sm text-slate-500">Received {{ $selectedReceipt['received_at'] ?? '-' }} by {{ $selectedReceipt['receiver'] }}</p>
                        </div>
                        <button type="button" wire:click="closeDetails" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Order</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $selectedReceipt['po_number'] }}</p>
                            <p class="text-xs text-slate-500">Vendor: {{ $selectedReceipt['vendor_name'] }}</p>
                            <p class="mt-2 text-xs text-slate-500">PO Status: {{ ucfirst(str_replace('_', ' ', (string) $selectedReceipt['po_status'])) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Receipt Totals</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">Lines: {{ $selectedReceipt['line_count'] }}</p>
                            <p class="text-xs text-slate-500">Qty Received: {{ number_format((float) $selectedReceipt['received_quantity_total'], 2) }}</p>
                            <p class="text-xs text-slate-500">Value Received: {{ strtoupper((string) $selectedReceipt['currency_code']) }} {{ number_format((int) $selectedReceipt['received_value_total']) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-800">Received Line Items</p>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="text-slate-500">
                                    <tr>
                                        <th class="py-1 text-left">Line</th>
                                        <th class="py-1 text-left">Description</th>
                                        <th class="py-1 text-right">Ordered Qty</th>
                                        <th class="py-1 text-right">Received Qty</th>
                                        <th class="py-1 text-right">Unit Cost</th>
                                        <th class="py-1 text-right">Received Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($selectedReceipt['items'] as $item)
                                        <tr>
                                            <td class="py-1 text-slate-700">{{ $item['line_number'] }}</td>
                                            <td class="py-1 text-slate-700">{{ $item['description'] }}</td>
                                            <td class="py-1 text-right text-slate-600">{{ number_format((float) $item['ordered_quantity'], 2) }}</td>
                                            <td class="py-1 text-right text-slate-600">{{ number_format((float) $item['received_quantity'], 2) }}</td>
                                            <td class="py-1 text-right text-slate-600">{{ number_format((int) $item['received_unit_cost']) }}</td>
                                            <td class="py-1 text-right text-slate-700">{{ number_format((int) $item['received_total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-800">Linked Vendor Invoices</p>
                        <div class="mt-2 space-y-1">
                            @forelse ($selectedReceipt['linked_invoices'] as $invoice)
                                <p class="text-xs text-slate-600">{{ $invoice['invoice_number'] }} | {{ $invoice['invoice_date'] ?? '-' }} | {{ $invoice['currency'] }} {{ number_format((int) $invoice['total_amount']) }} | Outstanding {{ number_format((int) $invoice['outstanding_amount']) }}</p>
                            @empty
                                <p class="text-xs text-slate-500">No invoices linked to this purchase order yet.</p>
                            @endforelse
                        </div>
                    </div>

                    @if ($selectedReceipt['notes'])
                        <div class="mt-4 rounded-xl border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-800">Receipt Notes</p>
                            <p class="mt-2 text-xs text-slate-600">{{ $selectedReceipt['notes'] }}</p>
                        </div>
                    @endif

                    <div class="mt-4 flex justify-end border-t border-slate-200 pt-4">
                        <button type="button" wire:click="closeDetails" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
