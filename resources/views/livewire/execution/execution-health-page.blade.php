<div wire:init="loadData" class="space-y-6">
<section class="fd-card p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Execution Health</p>
        <h2 class="mt-1 text-xl font-semibold text-slate-900">Execution Health</h2>
        <p class="mt-1 text-sm text-slate-600">Status view for your organization only. No raw provider diagnostics are exposed on this page.</p>
    </section>

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-3 h-3 w-32 rounded bg-slate-200"></div>
                    <div class="mb-2 h-7 w-20 rounded bg-slate-200"></div>
                    <div class="h-3 w-40 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @else
        @php
            $statusTone = (string) ($summary['status_tone'] ?? 'healthy');
            $statusClasses = match ($statusTone) {
                'action_needed' => 'border-rose-300 bg-rose-50 text-rose-900',
                'delayed' => 'border-amber-300 bg-amber-50 text-amber-900',
                default => 'border-emerald-300 bg-emerald-50 text-emerald-900',
            };
        @endphp

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border p-5 {{ $statusClasses }}">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Current Status</p>
                <p class="mt-2 text-2xl font-semibold">{{ $summary['status_label'] ?? 'Healthy' }}</p>
                @if (! empty($summary['current_incident_id']))
                    <p class="mt-2 text-xs">Incident ID: {{ $summary['current_incident_id'] }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Last Recovery Outcome</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $summary['last_recovery_outcome_at'] ?? 'No recovery outcome yet.' }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Affected Billings</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) ($summary['affected_billings'] ?? 0)) }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Affected Payouts</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) ($summary['affected_payouts'] ?? 0)) }}</p>
            </div>
        </section>

        <section class="fd-card p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Next Action</p>
            <p class="mt-2 text-sm text-slate-800">{{ $summary['next_action'] ?? 'Retry later.' }}</p>
        </section>

        <section class="fd-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Recent Tenant Summaries</h3>
                    <p class="text-xs text-slate-500">Latest alert and recovery summaries for your tenant.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Incident ID</th>
                            <th class="px-3 py-2">Summary</th>
                            <th class="px-3 py-2">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentSummaries as $row)
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2 text-slate-700">{{ $row['incident_id'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $row['summary'] }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $row['occurred_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-6 text-center text-sm text-slate-500">No tenant execution summaries yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

