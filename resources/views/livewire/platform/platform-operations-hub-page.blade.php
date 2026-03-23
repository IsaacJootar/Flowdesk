<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Platform Operations</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Operations Hub</h2>
                <p class="mt-1 text-sm text-slate-600">Single workspace for execution operations, checklist readiness, incident timeline, and pilot rollout controls.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Tenant organizations in scope: <span class="font-semibold text-slate-900">{{ number_format((int) $tenantCount) }}</span>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" wire:click="$set('tab', 'execution')" class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $tab === 'execution' ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">Execution Ops</button>
            <button type="button" wire:click="$set('tab', 'checklist')" class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $tab === 'checklist' ? 'border-amber-300 bg-amber-100 text-amber-800' : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' }}">Test Checklist</button>
            <button type="button" wire:click="$set('tab', 'incidents')" class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $tab === 'incidents' ? 'border-indigo-300 bg-indigo-100 text-indigo-800' : 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100' }}">Incident History</button>
            <button type="button" wire:click="$set('tab', 'rollout')" class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $tab === 'rollout' ? 'border-cyan-300 bg-cyan-100 text-cyan-800' : 'border-cyan-200 bg-cyan-50 text-cyan-700 hover:bg-cyan-100' }}">Pilot Rollout</button>
        </div>
    </div>

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-2 h-3 w-28 rounded bg-slate-200"></div>
                    <div class="h-8 w-20 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @elseif ($tab === 'execution')
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Billing Failed</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($executionSummary['billing_failed'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Payout Failed</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($executionSummary['payout_failed'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Webhook Failed/Invalid</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($executionSummary['webhook_failed'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Stuck Queued</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($executionSummary['stuck_queued'] ?? 0)) }}</p>
                <p class="mt-1 text-xs text-indigo-700">Threshold: {{ number_format((int) ($executionSummary['threshold_minutes'] ?? 0)) }} mins</p>
            </div>
        </section>

        <section class="fd-card border border-emerald-200 bg-emerald-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Execution Control</p>
                    <p class="mt-1 text-sm text-slate-700">Latest recovery action: {{ $executionSummary['last_recovery'] ?? 'No recovery actions logged yet.' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('platform.operations.ai-runtime-health') }}" class="inline-flex h-9 items-center rounded-lg border border-emerald-300 bg-white px-3 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Open AI Runtime Health</a>
                    <a href="{{ route('platform.operations.execution') }}" class="inline-flex h-9 items-center rounded-lg border border-emerald-300 bg-emerald-100 px-3 text-xs font-semibold text-emerald-800 hover:bg-emerald-200">Open Full Execution Operations</a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Scheduler Heartbeat</p>
                <p class="mt-2 text-sm font-semibold">{{ $runtimeHealth['scheduler_heartbeat_at'] ?? 'No heartbeat yet' }}</p>
                <p class="mt-1 text-xs text-sky-700">Delay: {{ $runtimeHealth['scheduler_delay_minutes'] !== null ? number_format((int) $runtimeHealth['scheduler_delay_minutes']).' mins' : 'Unknown' }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Failed Jobs (24h)</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($runtimeHealth['failed_jobs_last_24h'] ?? 0)) }}</p>
                <p class="mt-1 text-xs text-rose-700">Total failed jobs: {{ number_format((int) ($runtimeHealth['failed_jobs_total'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Queued Jobs</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($runtimeHealth['queued_jobs_total'] ?? 0)) }}</p>
                <p class="mt-1 text-xs text-indigo-700">Stale queued jobs: {{ number_format((int) ($runtimeHealth['stale_jobs_total'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Runtime Notes</p>
                <p class="mt-2 text-sm font-semibold">{{ $runtimeHealth['available'] ? 'Runtime health tables available' : 'Runtime health limited' }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ $runtimeHealth['note'] ?? 'Heartbeat, failed jobs, and queued backlog are being tracked.' }}</p>
            </div>
        </section>
    @elseif ($tab === 'checklist')
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Active Tenants</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($checklistSummary['active_tenants'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Execution Enabled Tenants</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($checklistSummary['execution_enabled_tenants'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Latest Tenant</p>
                <p class="mt-2 text-sm font-semibold">{{ $checklistSummary['latest_tenant'] ?? 'N/A' }}</p>
            </div>
        </section>

        <section class="fd-card border border-amber-200 bg-amber-50 p-5">
            <h3 class="text-sm font-semibold text-slate-900">Execution Test Run Flow</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Open tenant profile and confirm lifecycle is active.</li>
                <li>Set execution mode + provider policy for the selected tenant.</li>
                <li>Trigger sample billing/payout rows and verify queue transitions.</li>
                <li>Use execution operations to retry/recover/reconcile and confirm audit writes.</li>
            </ol>
            <div class="mt-4">
                <a href="{{ route('platform.operations.execution-checklist') }}" class="inline-flex h-9 items-center rounded-lg border border-amber-300 bg-amber-100 px-3 text-xs font-semibold text-amber-800 hover:bg-amber-200">Open Full Checklist Page</a>
            </div>
        </section>

        <section class="fd-card border border-slate-200 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Production Validation</h3>
                    <p class="mt-1 text-xs text-slate-500">These checks turn the README checklist into an executable launch gate for production.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 font-semibold text-rose-700">Blocking: {{ number_format((int) ($validationSummary['blocking'] ?? 0)) }}</span>
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-semibold text-amber-700">Warnings: {{ number_format((int) ($validationSummary['warning'] ?? 0)) }}</span>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @forelse (($validationSummary['issues'] ?? []) as $issue)
                    <div class="rounded-xl border px-3 py-2 text-sm {{ ($issue['severity'] ?? '') === 'critical' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                        <p class="font-semibold">{{ strtoupper((string) ($issue['severity'] ?? 'warning')) }} · {{ $issue['code'] ?? 'issue' }}</p>
                        <p class="mt-1">{{ $issue['message'] ?? 'Validation issue detected.' }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        Production validation is currently clean for the checks Flowdesk can verify automatically.
                    </div>
                @endforelse
            </div>
        </section>
    @elseif ($tab === 'incidents')
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Total Incidents (7d)</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($incidentSummary['total_7d'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Manual Ops (7d)</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($incidentSummary['manual_7d'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Auto/System (7d)</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($incidentSummary['auto_7d'] ?? 0)) }}</p>
            </div>
        </section>

        <section class="fd-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Recent Incident Events</h3>
                    <p class="text-xs text-slate-500">Latest recovery and rollout signals across tenant organizations.</p>
                </div>
                <a href="{{ route('platform.operations.incident-history') }}" class="inline-flex h-9 items-center rounded-lg border border-indigo-300 bg-indigo-100 px-3 text-xs font-semibold text-indigo-800 hover:bg-indigo-200">Open Full Incident History</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Tenant</th>
                            <th class="px-3 py-2">Pipeline</th>
                            <th class="px-3 py-2">Action</th>
                            <th class="px-3 py-2">Actor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($incidentSummary['recent'] ?? []) as $row)
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2 text-slate-500">{{ $row['time'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $row['tenant'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $row['pipeline'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $row['action'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $row['actor'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">No incident events available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        @if (! ($rolloutSummary['available'] ?? false))
            <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $rolloutSummary['error'] ?? 'Pilot rollout metrics are unavailable.' }}
            </section>
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">KPI Captures</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($rolloutSummary['captures'] ?? 0)) }}</p></div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Tenants Covered</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($rolloutSummary['tenants_covered'] ?? 0)) }}</p></div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Go</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($rolloutSummary['go'] ?? 0)) }}</p></div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Hold</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($rolloutSummary['hold'] ?? 0)) }}</p></div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">No-go</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($rolloutSummary['no_go'] ?? 0)) }}</p></div>
            </section>

            <section class="fd-card p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Recent Pilot Outcomes</h3>
                        <p class="text-xs text-slate-500">Latest go/hold/no-go decisions across pilot waves.</p>
                    </div>
                    <a href="{{ route('platform.operations.pilot-rollout') }}" class="inline-flex h-9 items-center rounded-lg border border-cyan-300 bg-cyan-100 px-3 text-xs font-semibold text-cyan-800 hover:bg-cyan-200">Open Full Pilot Rollout</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                                <th class="px-3 py-2">Time</th>
                                <th class="px-3 py-2">Tenant</th>
                                <th class="px-3 py-2">Wave</th>
                                <th class="px-3 py-2">Outcome</th>
                                <th class="px-3 py-2">Decided By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($rolloutSummary['recent_outcomes'] ?? []) as $row)
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-2 text-slate-500">{{ $row['time'] }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $row['tenant'] }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $row['wave'] }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $row['outcome'] }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $row['decided_by'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">No pilot outcomes recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
