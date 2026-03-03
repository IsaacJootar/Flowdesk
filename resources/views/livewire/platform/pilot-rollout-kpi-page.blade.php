<div wire:init="loadData" class="space-y-5">
    @if ($feedbackMessage !== '')
        <div
            wire:key="pilot-kpi-feedback-{{ $feedbackKey }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            class="pointer-events-none fixed z-[90]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div @class([
                'pointer-events-auto rounded-xl px-4 py-3 text-sm shadow-lg border',
                'border-emerald-200 bg-emerald-50 text-emerald-700' => $feedbackTone === 'success',
                'border-amber-200 bg-amber-50 text-amber-700' => $feedbackTone === 'warning',
                'border-rose-200 bg-rose-50 text-rose-700' => $feedbackTone === 'error',
            ])>
                {{ $feedbackMessage }}
            </div>
        </div>
    @endif

    <div class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rollout Operations</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Pilot KPI Capture</h2>
                <p class="mt-1 text-sm text-slate-600">Capture baseline and pilot windows for procurement + treasury control KPIs, then review tenant-level deltas.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('platform.operations.execution') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Execution Operations</a>
                <a href="{{ route('platform.operations.incident-history') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Incident History</a>
            </div>
        </div>
    </div>

    <section class="fd-card p-4">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-slate-900">Capture Window</h3>
            <p class="text-xs text-slate-500">Run KPI capture for all eligible tenants or one selected tenant.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Scope</span>
                <select wire:model.defer="captureTenant" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All eligible tenants</option>
                    @foreach ($tenantOptions as $tenant)
                        <option value="{{ (int) $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
                @error('captureTenant')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Window Label</span>
                <select wire:model.defer="captureWindowLabel" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="baseline">Baseline</option>
                    <option value="pilot">Pilot</option>
                    <option value="custom">Custom</option>
                </select>
                @error('captureWindowLabel')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Window Days</span>
                <input type="number" min="1" max="90" wire:model.defer="captureWindowDays" class="w-full rounded-xl border-slate-300 text-sm" />
                @error('captureWindowDays')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Window End</span>
                <input type="date" wire:model.defer="captureDateEnd" class="w-full rounded-xl border-slate-300 text-sm" title="Optional" />
                @error('captureDateEnd')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Operator Notes</span>
                <input type="text" wire:model.defer="captureNotes" maxlength="500" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional context" />
                @error('captureNotes')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>
        </div>

        <div class="mt-4">
            <button type="button" wire:click="captureNow" wire:loading.attr="disabled" wire:target="captureNow" class="inline-flex h-10 items-center rounded-xl bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="captureNow">Capture KPI Window</span>
                <span wire:loading wire:target="captureNow">Capturing...</span>
            </button>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Captured Windows</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $stats['captures']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Tenants Covered</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $stats['tenants']) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Avg Match Pass Rate</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((float) $stats['avg_match_pass_rate_percent'], 1) }}%</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Avg Auto Recon Rate</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((float) $stats['avg_auto_reconciliation_rate_percent'], 1) }}%</p>
        </div>
    </section>

    @if ($delta)
        <section class="fd-card p-4">
            <h3 class="text-sm font-semibold text-slate-900">Latest Baseline vs Pilot Delta</h3>
            <p class="mt-1 text-xs text-slate-500">Deltas are calculated from the most recent baseline and pilot captures for the selected tenant.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5 text-sm">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Match Pass Rate</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $delta['match_pass_rate_delta'], 2) }} pts</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Auto Recon Rate</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $delta['auto_reconciliation_rate_delta'], 2) }} pts</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Open Procurement Exceptions</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((int) $delta['open_procurement_exceptions_delta']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Open Treasury Exceptions</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((int) $delta['open_treasury_exceptions_delta']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Incident Rate / Week</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $delta['incident_rate_per_week_delta'], 2) }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="fd-card p-4">
        <div class="mb-3 grid gap-4 md:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant</span>
                <select wire:model.live="tenantFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All tenants</option>
                    @foreach ($tenantOptions as $tenant)
                        <option value="{{ (int) $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Window</span>
                <select wire:model.live="windowFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All windows</option>
                    <option value="baseline">Baseline</option>
                    <option value="pilot">Pilot</option>
                    <option value="custom">Custom</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rows Per Page</span>
                <select wire:model.live="perPage" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="15">15</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Captured</th>
                        <th class="px-3 py-2">Tenant</th>
                        <th class="px-3 py-2">Window</th>
                        <th class="px-3 py-2">Match Pass</th>
                        <th class="px-3 py-2">Open Proc Ex</th>
                        <th class="px-3 py-2">Auto Recon</th>
                        <th class="px-3 py-2">Open Treasury Ex</th>
                        <th class="px-3 py-2">Blocked Payouts</th>
                        <th class="px-3 py-2">Overrides</th>
                        <th class="px-3 py-2">Incidents / Week</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($captures as $row)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 text-slate-500">{{ $row->captured_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $row->company?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">
                                <p class="font-medium">{{ ucfirst((string) $row->window_label) }}</p>
                                <p class="text-xs text-slate-500">{{ $row->window_start?->format('M d') }} - {{ $row->window_end?->format('M d, Y') }}</p>
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((float) $row->match_pass_rate_percent, 1) }}%</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) $row->open_procurement_exceptions) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((float) $row->auto_reconciliation_rate_percent, 1) }}%</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) $row->open_treasury_exceptions) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) $row->blocked_payout_count) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) $row->manual_override_count) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((float) $row->incident_rate_per_week, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-sm text-slate-500">No pilot KPI captures found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $captures->links() }}</div>
    </section>
</div>

