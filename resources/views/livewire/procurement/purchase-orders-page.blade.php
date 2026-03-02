<div wire:init="loadData" class="space-y-5">
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

    <div class="grid gap-3 sm:grid-cols-3">
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
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($orders as $order)
                            @php
                                $statusClass = (string) $order->po_status === 'issued'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : ((string) $order->po_status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $order->po_number }}</p>
                                    <p class="text-xs text-slate-500">{{ $order->items_count }} line(s) | {{ $order->commitments_count }} commitment(s)</p>
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
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="openDetails({{ $order->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No procurement orders found for the selected filters.</td>
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
                <div wire:click.stop class="fd-card w-full max-w-4xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">Procurement Order</span>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $selectedOrder['po_number'] }}</h2>
                            <p class="text-sm text-slate-500">Issue roles: {{ implode(', ', array_map('ucfirst', $issueRoles)) }}</p>
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
                        <p class="text-sm font-semibold text-slate-800">Line Items</p>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="text-slate-500">
                                    <tr>
                                        <th class="py-1 text-left">#</th>
                                        <th class="py-1 text-left">Description</th>
                                        <th class="py-1 text-right">Qty</th>
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

                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        @if ($selectedOrder['can_issue'])
                            <button type="button" wire:click="issueSelectedOrder" wire:loading.attr="disabled" wire:target="issueSelectedOrder" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:opacity-70">
                                <span wire:loading.remove wire:target="issueSelectedOrder">Issue PO</span>
                                <span wire:loading wire:target="issueSelectedOrder">Issuing...</span>
                            </button>
                        @endif
                        <button type="button" wire:click="closeDetails" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
