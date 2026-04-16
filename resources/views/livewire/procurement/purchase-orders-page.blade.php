<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="purchase-orders"
        title="Purchase Orders"
        description="Formal orders raised to suppliers after spend requests are approved. A purchase order locks the price, quantity, and delivery terms before goods or services are received."
        :bullets="[
            'POs are generated from approved spend requests — no manual duplication needed.',
            'Suppliers receive a copy; your team tracks delivery and receipt against the PO.',
            'Three-way matching (PO → receipt → invoice) happens automatically in the procurement workspace.',
        ]"
    />
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Purchase Orders</h2>
            <p class="text-xs text-slate-500">Create and issue purchase orders from approved requests.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Release Desk</span>
            </a>
            <a href="{{ route('procurement.release-help') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">
                Purchase Order Guide
            </a>
        </div>
    </div>
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div wire:key="procurement-feedback-success-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3200)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                {{ $feedbackMessage }}
            </div>
        @endif
        @if ($feedbackError)
            <div wire:key="procurement-feedback-error-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg">
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-4">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="PO number, request code, vendor">
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

            <div class="flex items-end justify-end">
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

    <div class="flex justify-end gap-2">
        <a href="{{ route('procurement.receipts') }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Goods Receipts</a>
        <a href="{{ route('procurement.match-exceptions') }}" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Invoice Mismatches</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Orders</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $summary['total']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Draft</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $summary['draft']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Issued</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $summary['issued']) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-indigo-700">Part Received</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ number_format((int) $summary['receiving']) }}</p>
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
                            <th class="px-4 py-3 text-left font-semibold">Order</th>
                            <th class="px-4 py-3 text-left font-semibold">Request</th>
                            <th class="px-4 py-3 text-left font-semibold">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Progress</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($orders as $order)
                            @php
                                $statusClass = match ((string) $order->po_status) {
                                    'issued' => 'bg-emerald-100 text-emerald-700',
                                    'draft' => 'bg-amber-100 text-amber-700',
                                    'part_received' => 'bg-indigo-100 text-indigo-700',
                                    'received' => 'bg-sky-100 text-sky-700',
                                    'invoiced' => 'bg-violet-100 text-violet-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $order->po_number }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->items_count }} {{ Str::plural('line', $order->items_count) }} · {{ $order->commitments_count }} {{ Str::plural('commitment', $order->commitments_count) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ $order->request?->request_code ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->request?->title ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $order->vendor?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ strtoupper((string) $order->currency_code) }} {{ number_format((int) $order->total_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $order->po_status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    <p>{{ (int) $order->receipts_count }} receipt(s)</p>
                                    <p>{{ (int) $order->vendor_invoices_count }} linked invoice(s)</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="openDetails({{ $order->id }})" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">
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
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No purchase orders match the selected filters. Purchase orders are created from approved spend requests. Try clearing your filters or check that requests have been approved and converted.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Showing {{ $orders->firstItem() ?? 0 }}-{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }}</p>
                    {{ $orders->links() }}
                </div>
            </div>
        @endif
    </div>

    @if ($showDetailsModal && $selectedOrder)
        <div wire:click="closeDetails" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-5xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">Purchase Order</span>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $selectedOrder['po_number'] }}</h2>
                        </div>
                        <button type="button" wire:click="closeDetails" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Request</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $selectedOrder['request_code'] }}</p>
                            <p class="text-xs text-slate-500">{{ $selectedOrder['request_title'] }}</p>
                            <p class="mt-2 text-xs text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $selectedOrder['request_status'])) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Vendor & Amount</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $selectedOrder['vendor_name'] }}</p>
                            <p class="text-xs text-slate-500">{{ strtoupper((string) $selectedOrder['currency_code']) }} {{ number_format((int) $selectedOrder['total_amount']) }}</p>
                            <p class="mt-2 text-xs text-slate-500">Expected delivery: {{ $selectedOrder['expected_delivery_at'] ?? '-' }}</p>
                            <p class="text-xs text-slate-500">Issued at: {{ $selectedOrder['issued_at'] ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-800">Line Items and Receipt Progress</p>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="text-slate-500">
                                    <tr>
                                        <th class="py-1 text-left">#</th>
                                        <th class="py-1 text-left">Description</th>
                                        <th class="py-1 text-right">Ordered Qty</th>
                                        <th class="py-1 text-right">Received Qty</th>
                                        <th class="py-1 text-right">Remaining Qty</th>
                                        <th class="py-1 text-right">Unit Price</th>
                                        <th class="py-1 text-right">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($selectedOrder['items'] as $item)
                                        <tr>
                                            <td class="py-1 text-slate-700">{{ $item['line_number'] }}</td>
                                            <td class="py-1 text-slate-700">{{ $item['item_description'] }}</td>
                                            <td class="py-1 text-right text-slate-600">{{ number_format((float) $item['quantity'], 2) }}</td>
                                            <td class="py-1 text-right text-slate-600">{{ number_format((float) $item['received_quantity'], 2) }}</td>
                                            <td class="py-1 text-right text-slate-700">{{ number_format((float) $item['remaining_quantity'], 2) }}</td>
                                            <td class="py-1 text-right text-slate-600">{{ number_format((int) $item['unit_price']) }}</td>
                                            <td class="py-1 text-right text-slate-700">{{ number_format((int) $item['line_total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-800">Commitments</p>
                            <span class="text-xs text-slate-500">Total committed: {{ strtoupper((string) $selectedOrder['currency_code']) }} {{ number_format((int) $selectedOrder['commitment_total']) }}</span>
                        </div>
                        <div class="mt-2 space-y-1">
                            @forelse ($selectedOrder['commitments'] as $commitment)
                                <p class="text-xs text-slate-600">{{ ucfirst((string) $commitment['status']) }} | {{ number_format((int) $commitment['amount']) }} | {{ $commitment['effective_at'] ?? '-' }}</p>
                            @empty
                                <p class="text-xs text-slate-500">No commitments posted yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-800">Goods Receipts</p>
                            <p class="mt-1 text-xs text-slate-500">Recorded receipts: {{ (int) $selectedOrder['receipt_count'] }}</p>

                            @if ($selectedOrder['can_receive'])
                                <div class="mt-3 rounded-lg border border-indigo-100 bg-indigo-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-indigo-700">Record Delivery</p>
                                    <p class="mt-1 text-xs text-indigo-700">Over-receipt allowed: {{ $selectedOrder['allow_over_receipt'] ? 'Yes' : 'No' }}</p>

                                    <div class="mt-2 space-y-2">
                                        <label class="block">
                                            <span class="mb-1 block text-xs text-slate-600">Received At</span>
                                            <input type="datetime-local" wire:model.defer="receiptForm.received_at" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs text-slate-600">Notes</span>
                                            <textarea wire:model.defer="receiptForm.notes" rows="2" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500" placeholder="Optional receiving notes"></textarea>
                                        </label>
                                    </div>

                                    <div class="mt-3 space-y-2">
                                        @foreach ($receiptForm['items'] as $index => $line)
                                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                                <p class="text-xs font-medium text-slate-700">Line {{ $line['line_number'] }}: {{ $line['item_description'] }}</p>
                                                <p class="text-[11px] text-slate-500">Remaining: {{ number_format((float) $line['remaining_quantity'], 2) }}</p>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <label class="block">
                                                        <span class="mb-1 block text-[11px] text-slate-500">Receive Qty</span>
                                                        <input type="number" min="0" step="0.01" wire:model.defer="receiptForm.items.{{ $index }}.receive_quantity" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                                    </label>
                                                    <label class="block">
                                                        <span class="mb-1 block text-[11px] text-slate-500">Unit Cost</span>
                                                        <input type="number" min="1" step="1" wire:model.defer="receiptForm.items.{{ $index }}.received_unit_cost" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 flex justify-end">
                                        <button type="button" wire:click="submitGoodsReceipt" wire:loading.attr="disabled" wire:target="submitGoodsReceipt" class="rounded-lg border border-indigo-200 bg-indigo-100 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-200">
                                            <span wire:loading.remove wire:target="submitGoodsReceipt">Record Goods Receipt</span>
                                            <span wire:loading wire:target="submitGoodsReceipt">Recording...</span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3 space-y-1">
                                @forelse ($selectedOrder['receipts'] as $receipt)
                                    <div class="rounded-lg border border-slate-200 p-2 text-xs text-slate-600">
                                        <p class="font-medium text-slate-800">{{ $receipt['receipt_number'] }} | {{ $receipt['received_at'] }}</p>
                                        <p>Lines: {{ $receipt['line_count'] }} | Qty: {{ number_format((float) $receipt['received_quantity'], 2) }} | Value: {{ number_format((int) $receipt['received_total']) }}</p>
                                        @if ($receipt['notes'])
                                            <p class="text-slate-500">{{ $receipt['notes'] }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500">No goods receipts yet.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-800">Vendor Invoices</p>
                            <p class="mt-1 text-xs text-slate-500">Linked invoices: {{ (int) $selectedOrder['linked_invoice_count'] }}</p>

                            @if ($selectedOrder['can_link_invoice'])
                                <div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-emerald-700">Attach an Invoice</p>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-[1fr,auto]">
                                        <select wire:model="selectedVendorInvoiceId" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                            @foreach ($selectedOrder['selectable_invoices'] as $invoiceOption)
                                                <option value="{{ $invoiceOption['id'] }}">{{ $invoiceOption['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" wire:click="linkSelectedVendorInvoice" wire:loading.attr="disabled" wire:target="linkSelectedVendorInvoice" class="rounded-lg border border-emerald-200 bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">
                                            <span wire:loading.remove wire:target="linkSelectedVendorInvoice">Attach</span>
                                            <span wire:loading wire:target="linkSelectedVendorInvoice">Attaching...</span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3 space-y-1">
                                @forelse ($selectedOrder['linked_invoices'] as $invoice)
                                    <div class="rounded-lg border border-slate-200 p-2 text-xs text-slate-600">
                                        <p class="font-medium text-slate-800">{{ $invoice['invoice_number'] }} | {{ $invoice['invoice_date'] ?? '-' }}</p>
                                        <p>{{ strtoupper((string) $invoice['currency']) }} {{ number_format((int) $invoice['total_amount']) }} | Outstanding {{ number_format((int) $invoice['outstanding_amount']) }}</p>
                                        <p class="text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', (string) $invoice['status'])) }}</p>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500">No invoices linked yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-800">Activity Timeline</p>
                        <div class="mt-2 space-y-1">
                            @forelse ($selectedOrder['timeline'] as $event)
                                <p class="text-xs text-slate-600">{{ $event['at'] ?? '-' }} | {{ $event['label'] }} @if (! empty($event['meta'])) | {{ $event['meta'] }} @endif</p>
                            @empty
                                <p class="text-xs text-slate-500">No timeline events yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        @if ($selectedOrder['can_issue'])
                            <button type="button" wire:click="issueSelectedOrder" wire:loading.attr="disabled" wire:target="issueSelectedOrder" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:opacity-70">
                                <span wire:loading.remove wire:target="issueSelectedOrder">Send to Vendor</span>
                                <span wire:loading wire:target="issueSelectedOrder">Sending...</span>
                            </button>
                        @endif
                        <button type="button" wire:click="closeDetails" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
