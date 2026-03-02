<div wire:init="loadData" class="space-y-5">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="execution-ops-feedback-{{ $feedbackKey }}"
            class="pointer-events-none fixed z-[90]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg {{ $feedbackError ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                {{ $feedbackError ?: $feedbackMessage }}
            </div>
        </div>
    @endif

    <div class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Operations Center</p>
                <p class="mt-1 text-sm text-slate-600">Retry failures, process stuck queues, and manually reconcile webhook events across tenant execution pipelines.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('platform.operations.incident-history') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Incident History</a>
                <a href="{{ route('platform.operations.execution-checklist') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Test Checklist</a>
            </div>
        </div>
    </div>

    <div class="fd-card p-4">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Provider</span>
                <select wire:model.live="providerFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All providers</option>
                    @foreach ($providerOptions as $provider)
                        <option value="{{ $provider }}">{{ $provider }}</option>
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
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All statuses</option>
                    <option value="failed">Failed</option>
                    <option value="queued">Queued</option>
                    <option value="processing">Processing</option>
                    <option value="webhook_pending">Webhook pending</option>
                    <option value="settled">Settled</option>
                    <option value="reversed">Reversed</option>
                    <option value="ignored">Ignored</option>
                    <option value="invalid">Invalid (verification)</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Display Age Filter (mins)</span>
                <input type="number" min="1" max="43200" wire:model.blur="tableOlderThanMinutes" class="w-full rounded-xl border-slate-300 text-sm" />
                @error('tableOlderThanMinutes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Display Scope</span>
                <div class="flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3">
                    <input type="checkbox" wire:model.live="onlyOlderThan" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                    <span class="text-xs text-slate-700">Only show rows older than display filter</span>
                </div>
            </label>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Recovery Note</span>
                <input type="text" wire:model.defer="batchReason" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Reason for processing stuck queues" />
                @error('batchReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Recovery Age Threshold (mins)</span>
                <input type="number" min="1" max="43200" wire:model.blur="batchOlderThanMinutes" class="w-full rounded-xl border-slate-300 text-sm" />
                @error('batchOlderThanMinutes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <div class="flex flex-wrap items-end justify-end gap-2">
                <button type="button" wire:click="processStuckBillingQueued" wire:loading.attr="disabled" wire:target="processStuckBillingQueued" class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <span wire:loading.remove wire:target="processStuckBillingQueued">Run Billing Recovery</span>
                    <span wire:loading wire:target="processStuckBillingQueued">Processing...</span>
                </button>
                <button type="button" wire:click="processStuckPayoutQueued" wire:loading.attr="disabled" wire:target="processStuckPayoutQueued" class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <span wire:loading.remove wire:target="processStuckPayoutQueued">Run Payout Recovery</span>
                    <span wire:loading wire:target="processStuckPayoutQueued">Processing...</span>
                </button>
                <button type="button" wire:click="processStuckWebhookQueue" wire:loading.attr="disabled" wire:target="processStuckWebhookQueue" class="inline-flex h-10 items-center rounded-xl bg-slate-900 px-3 text-xs font-semibold text-white">
                    <span wire:loading.remove wire:target="processStuckWebhookQueue">Run Webhook Recovery</span>
                    <span wire:loading wire:target="processStuckWebhookQueue">Processing...</span>
                </button>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Recovery runs process up to 200 queued records per click. Age threshold uses queued time, not record creation time.</p>
        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">Runbook Hints</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <a href="{{ route('platform.operations.execution-checklist') }}#provider-config" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-slate-700 hover:bg-slate-100">Provider/config checks</a>
                <a href="{{ route('platform.operations.execution-checklist') }}#missing-request" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-slate-700 hover:bg-slate-100">Missing request</a>
                <a href="{{ route('platform.operations.execution-checklist') }}#missing-subscription" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-slate-700 hover:bg-slate-100">Missing subscription</a>
                <a href="{{ route('platform.operations.execution-checklist') }}#state-changed" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-slate-700 hover:bg-slate-100">State changed</a>
                <a href="{{ route('platform.operations.execution-checklist') }}#invalid-verification" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-slate-700 hover:bg-slate-100">Invalid verification</a>
                <a href="{{ route('platform.operations.execution-checklist') }}#missing-linked-attempt" class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-slate-700 hover:bg-slate-100">Missing linked attempt</a>
            </div>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Billing Failed</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) $stats['billing_failed']) }}</p>
        </div>
        <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-orange-700">Payout Failed</p>
            <p class="mt-2 text-2xl font-semibold text-orange-900">{{ number_format((int) $stats['payout_failed']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Webhook Failed / Invalid</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $stats['webhook_failed']) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Stuck Queued</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) $stats['stuck_queued']) }}</p>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Failure Rate ({{ (int) $stats['incident_window_minutes'] }}m)</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((float) $stats['failure_rate_percent'], 1) }}%</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Skipped Rate ({{ (int) $stats['incident_window_minutes'] }}m)</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((float) $stats['skipped_rate_percent'], 1) }}%</p>
        </div>
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-violet-700">Oldest Queue Age</p>
            <p class="mt-2 text-2xl font-semibold text-violet-900">
                @if ($stats['oldest_queue_age_minutes'] !== null)
                    {{ number_format((int) $stats['oldest_queue_age_minutes']) }} mins
                @else
                    -
                @endif
            </p>
        </div>
        <div class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-700">Last Recovery Outcome</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $stats['last_recovery_outcome'] ?? 'No recovery activity yet.' }}</p>
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Auto Recovery Runs</h3>
                <p class="text-xs text-slate-500">Scheduler/manual auto-recovery summaries grouped by timestamp and pipeline.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Timestamp</th>
                        <th class="px-3 py-2">Tenant</th>
                        <th class="px-3 py-2">Pipeline</th>
                        <th class="px-3 py-2">Provider</th>
                        <th class="px-3 py-2">Matched</th>
                        <th class="px-3 py-2">Processed</th>
                        <th class="px-3 py-2">Skipped</th>
                        <th class="px-3 py-2">Rejected</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($autoRecoveryRuns as $run)
                        @php
                            $meta = (array) ($run->metadata ?? []);
                            $pipeline = (string) ($meta['pipeline'] ?? '-');
                            $providerKey = (string) ($meta['provider_key'] ?? '-');
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-slate-500">{{ $run->event_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $run->company?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ ucfirst($pipeline) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $providerKey }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['matched'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['processed'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['skipped'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['rejected'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No auto recovery runs matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $autoRecoveryRuns->links() }}</div>
    </section>
    <section class="fd-card p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Alert Summaries</h3>
                <p class="text-xs text-slate-500">Tenant-specific rows emitted by scheduled `execution:ops:alert-summary` runs.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Timestamp</th>
                        <th class="px-3 py-2">Tenant</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Pipeline</th>
                        <th class="px-3 py-2">Provider</th>
                        <th class="px-3 py-2">Count</th>
                        <th class="px-3 py-2">Threshold</th>
                        <th class="px-3 py-2">Window</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alertSummaries as $summary)
                        @php
                            $meta = (array) ($summary->metadata ?? []);
                            $type = (string) ($meta['type'] ?? '-');
                            $pipeline = (string) ($meta['pipeline'] ?? '-');
                            $providerKey = (string) ($meta['provider_key'] ?? '-');
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-slate-500">{{ $summary->event_at?->format('M d, Y H:i') ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $summary->company?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ str_replace('_', ' ', $type) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ ucfirst($pipeline) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $providerKey }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['count'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['threshold'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($meta['window_minutes'] ?? 0)) }}m</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No alert summaries matched the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $alertSummaries->links() }}</div>
    </section>
    @if ($pipelineFilter === 'all' || $pipelineFilter === 'billing')
        <section class="fd-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Billing Attempts</h3>
                    <p class="text-xs text-slate-500">Subscription auto-billing pipeline failures and queued records.</p>
                </div>
                <div class="w-full max-w-md">
                    <input type="text" wire:model.defer="billingRetryReason" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Reason for manual retry" />
                    @error('billingRetryReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Attempt</th>
                            <th class="px-3 py-2">Tenant</th>
                            <th class="px-3 py-2">Provider</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Queued</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($billingAttempts as $attempt)
                            @php
                                $status = (string) $attempt->attempt_status;
                                $statusClass = match ($status) {
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'queued', 'webhook_pending', 'processing' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    'settled' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'reversed' => 'border-slate-300 bg-slate-100 text-slate-700',
                                    default => 'border-slate-300 bg-white text-slate-700',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2 text-slate-700">#{{ (int) $attempt->id }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $attempt->company?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $attempt->provider_key }}</td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ str_replace('_', ' ', $status) }}</span></td>
                                <td class="px-3 py-2 text-slate-700">{{ number_format((float) $attempt->amount, 2) }} {{ strtoupper((string) $attempt->currency_code) }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $attempt->queued_at?->format('M d, Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <button type="button" wire:click="retryBillingAttempt({{ (int) $attempt->id }})" wire:loading.attr="disabled" wire:target="retryBillingAttempt({{ (int) $attempt->id }})" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Retry</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">No billing attempts matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $billingAttempts->links() }}</div>
        </section>
    @endif

    @if ($pipelineFilter === 'all' || $pipelineFilter === 'payout')
        <section class="fd-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Payout Attempts</h3>
                    <p class="text-xs text-slate-500">Request payout execution attempts and terminal failures.</p>
                </div>
                <div class="w-full max-w-md">
                    <input type="text" wire:model.defer="payoutRetryReason" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Reason for manual retry" />
                    @error('payoutRetryReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Attempt</th>
                            <th class="px-3 py-2">Tenant</th>
                            <th class="px-3 py-2">Request</th>
                            <th class="px-3 py-2">Provider</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payoutAttempts as $attempt)
                            @php
                                $status = (string) $attempt->execution_status;
                                $statusClass = match ($status) {
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'queued', 'webhook_pending', 'processing' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    'settled' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'reversed' => 'border-slate-300 bg-slate-100 text-slate-700',
                                    default => 'border-slate-300 bg-white text-slate-700',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2 text-slate-700">#{{ (int) $attempt->id }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $attempt->company?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $attempt->request?->request_code ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $attempt->provider_key }}</td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ str_replace('_', ' ', $status) }}</span></td>
                                <td class="px-3 py-2 text-slate-700">{{ number_format((float) $attempt->amount, 2) }} {{ strtoupper((string) $attempt->currency_code) }}</td>
                                <td class="px-3 py-2">
                                    <button type="button" wire:click="retryPayoutAttempt({{ (int) $attempt->id }})" wire:loading.attr="disabled" wire:target="retryPayoutAttempt({{ (int) $attempt->id }})" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Retry</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">No payout attempts matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $payoutAttempts->links() }}</div>
        </section>
    @endif

    @if ($pipelineFilter === 'all' || $pipelineFilter === 'webhook')
        <section class="fd-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Webhook Dead-Letter / Reconciliation</h3>
                    <p class="text-xs text-slate-500">Manual reconciliation for queued/failed/invalid provider callbacks.</p>
                </div>
                <div class="w-full max-w-md">
                    <input type="text" wire:model.defer="webhookReconcileReason" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Reason for manual reconcile" />
                    @error('webhookReconcileReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Event</th>
                            <th class="px-3 py-2">Tenant</th>
                            <th class="px-3 py-2">Provider</th>
                            <th class="px-3 py-2">Event Type</th>
                            <th class="px-3 py-2">Verification</th>
                            <th class="px-3 py-2">Processing</th>
                            <th class="px-3 py-2">Received</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($webhookEvents as $event)
                            @php
                                $verificationClass = (string) $event->verification_status === 'invalid'
                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                    : ((string) $event->verification_status === 'valid'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-700');

                                $processingClass = match ((string) $event->processing_status) {
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'processed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'queued' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    default => 'border-slate-300 bg-white text-slate-700',
                                };
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2 text-slate-700">#{{ (int) $event->id }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $event->company?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $event->provider_key }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $event->event_type ?: '-' }}</td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $verificationClass }}">{{ $event->verification_status }}</span></td>
                                <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $processingClass }}">{{ $event->processing_status }}</span></td>
                                <td class="px-3 py-2 text-slate-500">{{ $event->received_at?->format('M d, Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <button type="button" wire:click="reconcileWebhookEvent({{ (int) $event->id }})" wire:loading.attr="disabled" wire:target="reconcileWebhookEvent({{ (int) $event->id }})" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reconcile</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No webhook events matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $webhookEvents->links() }}</div>
        </section>
    @endif
</div>


