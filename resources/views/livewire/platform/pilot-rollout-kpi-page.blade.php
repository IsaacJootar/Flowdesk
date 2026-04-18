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
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Platform Operations</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Payment Rollout Tracker</h2>
                <p class="mt-1 text-sm text-slate-600">Track the health of each organization before and after payments go live — then decide whether to proceed, pause, or stop.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('platform.operations.execution') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Execution Operations<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('platform.operations.incident-history') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Incident History<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>
    </div>

    <section class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        <p class="font-semibold">How this works — 3 steps per organization:</p>
        <ol class="mt-1 list-decimal space-y-0.5 pl-5 text-sky-800">
            <li><span class="font-semibold">Baseline snapshot</span> — record numbers before payments go live (issues, match rates, incidents).</li>
            <li><span class="font-semibold">Pilot snapshot</span> — record the same numbers after payments go live.</li>
            <li><span class="font-semibold">Decision</span> — compare the two and record: Go (proceed), Hold (pause), or No-go (stop).</li>
        </ol>
        <p class="mt-1.5 text-xs text-sky-700">The progress table at the bottom shows where each organization is in this process.</p>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-slate-900">Step 1 & 2 — Record a Snapshot</h3>
            <p class="text-xs text-slate-500">Take a numbers snapshot for one or all organizations. Choose <strong>Baseline</strong> before going live, <strong>Pilot</strong> after.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization</span>
                <select wire:model.defer="captureTenant" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All eligible organizations</option>
                    @foreach ($tenantOptions as $tenant)
                        <option value="{{ (int) $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
                @error('captureTenant')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Snapshot Type</span>
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Window End <span class="text-[10px] normal-case text-slate-400">(optional)</span></span>
                <input type="date" wire:model.defer="captureDateEnd" class="w-full rounded-xl border-slate-300 text-sm" />
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

    <section class="fd-card p-4">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-slate-900">Step 3 — Record a Rollout Decision</h3>
            <p class="text-xs text-slate-500">After reviewing the baseline vs pilot results, record your decision for this organization: Go, Hold, or No-go.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization</span>
                <select wire:model.defer="outcomeTenant" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="">Select organization</option>
                    @foreach ($tenantOptions as $tenant)
                        <option value="{{ (int) $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
                @error('outcomeTenant')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phase Name</span>
                <input type="text" wire:model.defer="outcomeWaveLabel" maxlength="40" class="w-full rounded-xl border-slate-300 text-sm" placeholder="e.g. wave-1" />
                @error('outcomeWaveLabel')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Outcome</span>
                <select wire:model.defer="outcomeDecision" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="go">Go</option>
                    <option value="hold">Hold</option>
                    <option value="no_go">No-go</option>
                </select>
                @error('outcomeDecision')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Decision Date <span class="text-[10px] normal-case text-slate-400">(optional)</span></span>
                <input type="date" wire:model.defer="outcomeDecisionAt" class="w-full rounded-xl border-slate-300 text-sm" />
                @error('outcomeDecisionAt')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Decision Notes</span>
                <input type="text" wire:model.defer="outcomeNotes" maxlength="500" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Why this decision?" />
                @error('outcomeNotes')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </label>
        </div>

        <div class="mt-4">
            <button type="button" wire:click="recordWaveOutcome" wire:loading.attr="disabled" wire:target="recordWaveOutcome" class="inline-flex h-10 items-center rounded-xl bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="recordWaveOutcome">Save Decision</span>
                <span wire:loading wire:target="recordWaveOutcome">Saving...</span>
            </button>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Snapshots Taken</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $stats['captures']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Organizations Covered</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $stats['tenants']) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Avg Payment Match Rate</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((float) $stats['avg_match_pass_rate_percent'], 1) }}%</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Avg Auto-Reconciliation Rate</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((float) $stats['avg_auto_reconciliation_rate_percent'], 1) }}%</p>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Go Outcomes</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $outcomeStats['go']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Hold Outcomes</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $outcomeStats['hold']) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">No-go Outcomes</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) $outcomeStats['no_go']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-700">Total Decisions</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) $outcomeStats['total']) }}</p>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Recent Rollout Decisions</h3>
                <p class="text-xs text-slate-500">The most recent Go / Hold / No-go decisions recorded across organizations.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Decision Time</th>
                        <th class="px-3 py-2">Organization</th>
                        <th class="px-3 py-2">Phase</th>
                        <th class="px-3 py-2">Decision</th>
                        <th class="px-3 py-2">Decided By</th>
                        <th class="px-3 py-2">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentOutcomes as $outcome)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 text-slate-500">{{ $outcome->decision_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $outcome->company?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $outcome->wave_label }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $outcome->outcome === 'go',
                                    'bg-amber-100 text-amber-700' => $outcome->outcome === 'hold',
                                    'bg-rose-100 text-rose-700' => $outcome->outcome === 'no_go',
                                ])>
                                    {{ $this->outcomeDisplayLabel((string) $outcome->outcome) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ $outcome->decidedBy?->name ?? 'System' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $outcome->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">No pilot wave outcomes have been recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-slate-900">Where Each Organization Is</h3>
            <p class="text-xs text-slate-500">Shows which step each organization is on — Baseline → Pilot → Decision.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Organization</th>
                        <th class="px-3 py-2">Baseline Captured</th>
                        <th class="px-3 py-2">Pilot Captured</th>
                        <th class="px-3 py-2">Outcome Recorded</th>
                        <th class="px-3 py-2">Stage</th>
                        <th class="px-3 py-2">Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cohortProgress as $row)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 text-slate-700 font-medium">{{ $row['tenant_name'] }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $row['baseline_done'],
                                    'bg-rose-100 text-rose-700' => ! $row['baseline_done'],
                                ])>
                                    {{ $row['baseline_done'] ? 'Captured' : 'Missing' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['baseline_captured_at'] ?? '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $row['pilot_done'],
                                    'bg-rose-100 text-rose-700' => ! $row['pilot_done'],
                                ])>
                                    {{ $row['pilot_done'] ? 'Captured' : 'Missing' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['pilot_captured_at'] ?? '-' }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $row['outcome_done'],
                                    'bg-rose-100 text-rose-700' => ! $row['outcome_done'],
                                ])>
                                    {{ $row['outcome_done'] ? ($row['outcome_label'] ?? 'Recorded') : 'Missing' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['outcome_recorded_at'] ?? '-' }}</p>
                                @if ($row['outcome_done'])
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $row['outcome_wave_label'] ?? '-' }}{{ ! empty($row['decided_by']) ? ' | '.$row['decided_by'] : '' }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $row['stage'] === 'Ready for rollout',
                                    'bg-amber-100 text-amber-700' => $row['stage'] === 'Decision pending' || $row['stage'] === 'Pilot capture pending',
                                    'bg-rose-100 text-rose-700' => $row['stage'] === 'Baseline pending',
                                ])>
                                    {{ $row['stage'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ $row['next_action'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">No eligible organizations found for cohort progress tracking.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($delta)
        <section class="fd-card p-4">
            <h3 class="text-sm font-semibold text-slate-900">Before vs After — What Changed</h3>
            <p class="mt-1 text-xs text-slate-500">Difference between the most recent baseline and pilot snapshots for this organization. Positive = improved, negative = got worse.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5 text-sm">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Payment Match Rate</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $delta['match_pass_rate_delta'], 2) }} pts</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Auto-Reconciliation Rate</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $delta['auto_reconciliation_rate_delta'], 2) }} pts</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Open Procurement Issues</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ number_format((int) $delta['open_procurement_exceptions_delta']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Open Treasury Issues</p>
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization</span>
                <select wire:model.live="tenantFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All organizations</option>
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
                        <th class="px-3 py-2">Organization</th>
                        <th class="px-3 py-2">Snapshot Type</th>
                        <th class="px-3 py-2">Match Pass</th>
                        <th class="px-3 py-2">Open Procurement Issues</th>
                        <th class="px-3 py-2">Auto-Match Rate</th>
                        <th class="px-3 py-2">Open Treasury Issues</th>
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
