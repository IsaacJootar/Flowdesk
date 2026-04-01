<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Incident Timeline</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Incident History</h2>
                <p class="mt-1 text-sm text-slate-600">Central history for manual retries/recoveries, auto-recovery runs, webhook reconciliation outcomes, and rollout decisions.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('platform.operations.execution-checklist') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Test Checklist</a>
                <a href="{{ route('platform.operations.pilot-rollout') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Pilot Rollout KPIs</a>
            </div>
        </div>
    </div>

    <div class="fd-card p-4">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Pipeline</span>
                <select wire:model.live="pipelineFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All pipelines</option>
                    <option value="billing">Billing</option>
                    <option value="payout">Payout</option>
                    <option value="webhook">Webhook</option>
                    <option value="procurement">Procurement</option>
                    <option value="treasury">Treasury</option>
                    <option value="system">System summaries</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Incident Type</span>
                <select wire:model.live="incidentTypeFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    @foreach ($incidentTypeOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Actor</span>
                <select wire:model.live="actorFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All actors</option>
                    <option value="user">Operator actions</option>
                    <option value="system">System actions</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Date From</span>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-xl border-slate-300 text-sm" />
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Date To</span>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-xl border-slate-300 text-sm" />
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rows Per Page</span>
                <select wire:model.live="perPage" class="rounded-xl border-slate-300 text-sm">
                    <option value="15">15</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </label>

            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv">Exporting...</span>
            </button>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Total Incidents</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $stats['total']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Manual Ops Actions</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $stats['manual']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Auto Recovery Events</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $stats['auto']) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Manual Reconcile Failed</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) $stats['manual_failed']) }}</p>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">7-Day Trend</h3>
                <p class="text-xs text-slate-500">Daily incident distribution by pipeline for the last seven days.</p>
            </div>
            <div class="text-xs text-slate-500">Max daily total: {{ number_format((int) ($trend['max_total'] ?? 0)) }}</div>
        </div>

        <div class="space-y-2">
            @forelse (($trend['rows'] ?? []) as $row)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center justify-between text-xs">
                        <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                        <span class="text-slate-500">Total {{ number_format((int) $row['total']) }}</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded bg-slate-200">
                        @php
                            $maxTotal = max(1, (int) ($trend['max_total'] ?? 1));
                            $billingWidth = ((int) $row['billing'] / $maxTotal) * 100;
                            $payoutWidth = ((int) $row['payout'] / $maxTotal) * 100;
                            $webhookWidth = ((int) $row['webhook'] / $maxTotal) * 100;
                            $systemWidth = ((int) $row['system'] / $maxTotal) * 100;
                        @endphp
                        <div class="flex h-2 w-full">
                            <span class="h-2 bg-sky-500" style="width: {{ $billingWidth }}%"></span>
                            <span class="h-2 bg-emerald-500" style="width: {{ $payoutWidth }}%"></span>
                            <span class="h-2 bg-amber-500" style="width: {{ $webhookWidth }}%"></span>
                            <span class="h-2 bg-slate-500" style="width: {{ $systemWidth }}%"></span>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-slate-600">
                        <span>Billing {{ (int) $row['billing'] }}</span>
                        <span>Payout {{ (int) $row['payout'] }}</span>
                        <span>Webhook {{ (int) $row['webhook'] }}</span>
                        <span>System {{ (int) $row['system'] }}</span>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    No incident records in the last seven days for the selected filters.
                </div>
            @endforelse
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Incident Events</h3>
                <p class="text-xs text-slate-500">Recovery and execution-operations audit entries with metadata drill-down.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Timestamp</th>
                        <th class="px-3 py-2">Organization</th>
                        <th class="px-3 py-2">Pipeline</th>
                        <th class="px-3 py-2">Incident Type</th>
                        <th class="px-3 py-2">Action</th>
                        <th class="px-3 py-2">Actor</th>
                        <th class="px-3 py-2">Details</th>
                        <th class="px-3 py-2">Metadata</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $event)
                        @php
                            $meta = (array) ($event->metadata ?? []);
                        @endphp
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-2 text-slate-500">{{ $event->event_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $event->company?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $this->pipelineLabelFor((string) $event->action, $meta) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $this->incidentTypeLabelForAction((string) $event->action) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $this->actionLabel((string) $event->action) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $event->actor?->name ?? 'System' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $this->detailsForEvent($event) }}</td>
                            <td class="px-3 py-2 text-slate-700">
                                @if ($meta !== [])
                                    <details>
                                        <summary class="cursor-pointer text-xs font-semibold text-slate-600">View</summary>
                                        <pre class="mt-2 max-w-[22rem] overflow-x-auto rounded-lg bg-slate-900 p-2 text-[11px] text-slate-100">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">No incident events matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $incidents->links() }}</div>
    </section>
</div>



