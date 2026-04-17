<div wire:init="loadData" class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="expense-handoff-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif

        @if ($feedbackError)
            <div
                wire:key="expense-handoff-feedback-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 4200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <section class="fd-card border border-emerald-200 bg-emerald-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('operations.control-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">&larr; Back to Operations Overview</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Expense Handoff</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Settled Payouts Needing Expense Records</h2>
                <p class="mt-1 text-sm text-slate-700">Review settled payments, post the linked expense, or record why no expense is required.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.financial-trace') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Financial Trace</a>
                <a href="{{ route('expenses.index') }}" class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-200">Expenses</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="expense-handoff-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Handoffs</label>
                <input id="expense-handoff-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Request code or title" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white px-3 py-2 text-xs text-slate-600 md:col-span-2">
                This page closes the gap between money leaving the account and the official expense ledger.
            </div>
        </div>
    </section>

    @if (! $readyToLoad)
        <section class="grid gap-4 md:grid-cols-3">
            @for ($i = 0; $i < 3; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-2 h-3 w-32 rounded bg-slate-200"></div>
                    <div class="h-7 w-20 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @else
        <section class="fd-card border border-slate-200 bg-white p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Pending Handoffs</h3>
                    <p class="text-xs text-slate-500">Each row is a settled payout that has no linked expense yet.</p>
                </div>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    {{ number_format($handoffs->total()) }} pending
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Request</th>
                            <th class="px-3 py-2">Payment</th>
                            <th class="px-3 py-2">Context</th>
                            <th class="px-3 py-2">Resolve</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="px-3 py-3">
                                    <a href="{{ $row['request_url'] }}" class="font-semibold text-slate-900 hover:text-emerald-700">{{ $row['request_code'] }}</a>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['title'] }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a href="{{ $row['trace_url'] }}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Trace</a>
                                        <a href="{{ $row['request_url'] }}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Request</a>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    <p class="font-semibold text-slate-900">{{ $row['amount'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['payment_method'] }} &middot; {{ $row['payment_status'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Settled {{ $row['settled_at'] }}</p>
                                    @if ($row['provider_reference'] !== '')
                                        <p class="mt-1 text-xs text-slate-500">{{ $row['provider_reference'] }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    <p>{{ $row['department'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['vendor'] }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            wire:click="createExpense({{ $row['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="createExpense({{ $row['id'] }})"
                                            @disabled(! $canCreateExpense)
                                            class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="createExpense({{ $row['id'] }})">Create Expense</span>
                                            <span wire:loading wire:target="createExpense({{ $row['id'] }})">Creating...</span>
                                        </button>
                                    </div>
                                    <div class="mt-3 flex min-w-[260px] flex-col gap-2">
                                        <input
                                            type="text"
                                            wire:model.defer="notRequiredReasons.{{ $row['id'] }}"
                                            placeholder="Reason if no expense is needed"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        >
                                        <button
                                            type="button"
                                            wire:click="markNotRequired({{ $row['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markNotRequired({{ $row['id'] }})"
                                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="markNotRequired({{ $row['id'] }})">Mark Not Required</span>
                                            <span wire:loading wire:target="markNotRequired({{ $row['id'] }})">Saving...</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-10 text-center text-sm text-slate-500">No settled payout handoffs are waiting for expense review.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $handoffs->links() }}
            </div>
        </section>
    @endif
</div>
