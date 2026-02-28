<div wire:init="loadData" class="space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Operations</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">{{ $company->slug }} · {{ $company->email ?: 'no email' }}</p>
        </div>
        <a href="{{ route('platform.tenants') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">
            Back to Tenants
        </a>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Plan</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ ucfirst((string) ($subscription?->plan_code ?? 'pilot')) }}</p>
            <p class="mt-1 text-xs text-sky-700">{{ ucfirst((string) ($subscription?->subscription_status ?? 'current')) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Ledger Balance</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ strtoupper((string) ($company->currency_code ?: 'NGN')) }} {{ number_format((float) $stats['balance'], 2) }}</p>
            <p class="mt-1 text-xs text-emerald-700">Credit minus debit entries</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Unapplied Payments</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ strtoupper((string) ($company->currency_code ?: 'NGN')) }} {{ number_format((float) $stats['unapplied'], 2) }}</p>
            <p class="mt-1 text-xs text-amber-700">Needs allocation/reconciliation</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Seat Usage</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) $stats['active_users']) }} / {{ $stats['seat_limit'] > 0 ? number_format((int) $stats['seat_limit']) : 'No limit' }}</p>
            <p class="mt-1 text-xs text-indigo-700">{{ number_format((float) $stats['seat_utilization'], 2) }}% utilization</p>
        </div>
        @php
            $warningClasses = match ((string) $stats['warning_level']) {
                'critical' => 'border-rose-200 bg-rose-50 text-rose-900',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
                default => 'border-slate-200 bg-slate-50 text-slate-900',
            };
        @endphp
        <div class="rounded-2xl border p-5 {{ $warningClasses }}">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Quota Warning</p>
            <p class="mt-2 text-2xl font-semibold">{{ ucfirst((string) $stats['warning_level']) }}</p>
            <p class="mt-1 text-xs">Threshold based on seat utilization</p>
        </div>
    </section>

    <div class="fd-card overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3">
            <p class="text-sm font-semibold text-slate-800">Billing Ledger</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-left font-semibold">Type</th>
                        <th class="px-4 py-3 text-left font-semibold">Direction</th>
                        <th class="px-4 py-3 text-left font-semibold">Amount</th>
                        <th class="px-4 py-3 text-left font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($ledgerEntries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ optional($entry->effective_date)->format('M d, Y') ?: '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ ucfirst((string) $entry->entry_type) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $entry->direction === 'credit' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    {{ ucfirst((string) $entry->direction) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ strtoupper((string) $entry->currency_code) }} {{ number_format((float) $entry->amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $entry->description ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $ledgerEntries->links() }}</div>
    </div>

    <div class="fd-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <p class="text-sm font-semibold text-slate-800">Reconciliation Queue</p>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                <span>Status</span>
                <select wire:model.live="allocationStatusFilter" class="rounded-lg border-slate-300 text-xs">
                    <option value="all">All</option>
                    <option value="unapplied">Unapplied</option>
                    <option value="allocated">Allocated</option>
                    <option value="reversed">Reversed</option>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Amount</th>
                        <th class="px-4 py-3 text-left font-semibold">Period</th>
                        <th class="px-4 py-3 text-left font-semibold">Reference</th>
                        <th class="px-4 py-3 text-left font-semibold">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($allocations as $allocation)
                        @php
                            $statusClass = match ((string) $allocation->allocation_status) {
                                'allocated' => 'bg-emerald-100 text-emerald-700',
                                'reversed' => 'bg-rose-100 text-rose-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst((string) $allocation->allocation_status) }}</span></td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ strtoupper((string) $allocation->currency_code) }} {{ number_format((float) $allocation->amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ optional($allocation->period_start)->format('M d, Y') ?: '-' }} to {{ optional($allocation->period_end)->format('M d, Y') ?: '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $allocation->manualPayment?->reference ?: '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $allocation->note ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No allocation records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $allocations->links() }}</div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="fd-card overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">Plan Change Timeline</p>
            </div>
            <div class="space-y-3 p-4">
                @forelse ($planHistory as $history)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ strtoupper((string) ($history->previous_plan_code ?: '-')) }} → {{ strtoupper((string) $history->new_plan_code) }}
                        </p>
                        <p class="text-xs text-slate-500">{{ optional($history->changed_at)->format('M d, Y H:i') }} · {{ $history->changer?->name ?: 'System' }}</p>
                        @if ($history->reason)
                            <p class="mt-1 text-xs text-slate-600">{{ $history->reason }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No plan changes recorded yet.</p>
                @endforelse
            </div>
        </div>

        <div class="fd-card overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">Usage Snapshots</p>
            </div>
            <div class="space-y-3 p-4">
                @forelse ($usageSnapshots as $snapshot)
                    @php
                        $usageClass = match ((string) $snapshot->warning_level) {
                            'critical' => 'border-rose-200 bg-rose-50',
                            'warning' => 'border-amber-200 bg-amber-50',
                            default => 'border-slate-200 bg-slate-50',
                        };
                    @endphp
                    <div class="rounded-xl border p-3 {{ $usageClass }}">
                        <p class="text-sm font-semibold text-slate-800">{{ optional($snapshot->snapshot_at)->format('M d, Y H:i') }}</p>
                        <p class="text-xs text-slate-600">Users: {{ number_format((int) $snapshot->active_users) }} / {{ $snapshot->seat_limit ? number_format((int) $snapshot->seat_limit) : 'No limit' }} · {{ number_format((float) ($snapshot->seat_utilization_percent ?? 0), 2) }}%</p>
                        <p class="text-xs text-slate-600">Req: {{ number_format((int) $snapshot->requests_count) }} · Exp: {{ number_format((int) $snapshot->expenses_count) }} · Ven: {{ number_format((int) $snapshot->vendors_count) }} · Assets: {{ number_format((int) $snapshot->assets_count) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No usage snapshots yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3">
            <p class="text-sm font-semibold text-slate-800">Tenant Audit Events</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Time</th>
                        <th class="px-4 py-3 text-left font-semibold">Action</th>
                        <th class="px-4 py-3 text-left font-semibold">Actor</th>
                        <th class="px-4 py-3 text-left font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($auditEvents as $event)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ optional($event->event_at)->format('M d, Y H:i') ?: '-' }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ $event->action }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $event->actor?->name ?: 'System' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $event->description ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No audit events recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $auditEvents->links() }}</div>
    </div>
</div>

