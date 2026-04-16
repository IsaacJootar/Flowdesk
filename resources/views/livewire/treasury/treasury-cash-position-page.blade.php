<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="treasury-cash-position"
        title="Cash Position"
        description="A live view of how much money your organisation has, where it sits, and what is scheduled to go out in the coming days."
        :bullets="[
            'Bank balances update automatically each time you sync via Mono Connect.',
            'Upcoming payment runs are shown so you can see what will leave your account.',
            'Use this page for daily liquidity checks and weekly cash planning conversations.',
        ]"
    />
    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Cash Position</p>
                <p class="mt-1 text-sm text-slate-600">See your bank balances and any transactions not yet matched to your records.</p>
            </div>
            <a href="{{ route('treasury.reconciliation') }}" class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Reconciliation</span>
            </a>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Bank Accounts</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format((int) $summary['accounts']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Closing Balance Total</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $summary['closing_balance_total']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">Unmatched Transactions</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $summary['unreconciled_count']) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Unreconciled Value</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $summary['unreconciled_value']) }}</p>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Bank</th>
                        <th class="px-4 py-3 text-left font-semibold">Account</th>
                        <th class="px-4 py-3 text-left font-semibold">Last Statement</th>
                        <th class="px-4 py-3 text-right font-semibold">Latest Closing Balance</th>
                        <th class="px-4 py-3 text-right font-semibold">Unmatched Count</th>
                        <th class="px-4 py-3 text-right font-semibold">Unmatched Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accountRows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">{{ $row['bank_name'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['account_name'] }} ({{ strtoupper((string) $row['currency_code']) }})</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['last_statement_at'] ?: '-' }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) $row['latest_closing_balance']) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) $row['unreconciled_count']) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) $row['unreconciled_value']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No treasury accounts yet. Configure bank accounts in Treasury Reconciliation.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
