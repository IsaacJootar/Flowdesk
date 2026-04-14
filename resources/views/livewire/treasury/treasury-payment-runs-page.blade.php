<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="treasury-payment-runs"
        title="Payment Runs"
        description="Payment runs are batches of approved vendor and reimbursement payments sent to your bank or payment provider together."
        :bullets="[
            'Runs are created automatically when enough approved payables accumulate, or you can trigger one manually.',
            'Each run goes through processing → settled → failed, tracked here in real time.',
            'Failed payments surface immediately so your team can investigate and retry without delay.',
        ]"
    />
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Runs</p>
                <p class="mt-1 text-sm text-slate-600">Review payout run health, then return to the main treasury workspace.</p>
            </div>
            <a href="{{ route('treasury.reconciliation') }}" class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Manage Treasury</span>
            </a>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Run Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Run Type</span>
                <select wire:model.live="typeFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end justify-end lg:col-span-2">
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
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Total Runs</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $summary['total']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Processing</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $summary['processing']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Completed</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $summary['completed']) }}</p>
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
                            <th class="px-4 py-3 text-left font-semibold">Run</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Type</th>
                            <th class="px-4 py-3 text-right font-semibold">Items</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($runs as $run)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-700">{{ $run->run_code }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst((string) $run->run_status) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst(str_replace('_', ' ', (string) $run->run_type)) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) $run->total_items) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ strtoupper((string) $run->currency_code) }} {{ number_format((int) $run->total_amount) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($run->created_at)->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No payment runs yet. Payment runs are batches of approved payments sent to your bank provider together. They appear here once your first approved payment is queued and dispatched.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Showing {{ $runs->firstItem() ?? 0 }}-{{ $runs->lastItem() ?? 0 }} of {{ $runs->total() }}</p>
                    {{ $runs->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

