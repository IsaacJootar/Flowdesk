<div wire:init="loadData" class="space-y-5">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div wire:key="treasury-exc-success-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3200)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                {{ $feedbackMessage }}
            </div>
        @endif

        @if ($feedbackError)
            <div wire:key="treasury-exc-error-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg">
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Treasury Reconciliation Exceptions</p>
                <p class="mt-1 text-sm text-slate-600">Resolve or waive exceptions, then return to the main treasury workspace.</p>
                <p class="mt-1 text-xs text-slate-500">Action roles: {{ implode(', ', (array) $exceptionActionAllowedRoles) }}.</p>
                @if ($makerCheckerRequired)
                    <p class="text-xs text-amber-700">Maker-checker is enabled: exception maker cannot close the same exception.</p>
                @endif
                @if ($flowAgentsEnabled)
                    <div class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                        <span class="font-semibold">Flow Agent:</span> use <span class="font-semibold">Use Flow Agent</span> for suggested match, confidence, and next-step guidance.
                        @if ($flowAgentsAdvisoryOnly)
                            Guidance is advisory only and does not auto-resolve exceptions.
                        @endif
                    </div>
                @endif
            </div>
            <a href="{{ route('treasury.reconciliation') }}" class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Manage Treasury</span>
            </a>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-6">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Code, details, line reference">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Severity</span>
                <select wire:model.live="severityFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($severities as $severity)
                        <option value="{{ $severity }}">{{ ucfirst($severity) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Stream</span>
                <select wire:model.live="streamFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($streams as $stream)
                        <option value="{{ $stream }}">{{ ucfirst(str_replace('_', ' ', $stream)) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Queue Mode</span>
                <select wire:model.live="queueSort" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($queueSortOptions as $sortValue => $sortLabel)
                        <option value="{{ $sortValue }}">{{ $sortLabel }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <p class="mt-3 text-xs text-slate-500">
            Priority queue uses severity, queue age, and transaction value. SLA breach threshold is {{ (int) $slaHours }} hour(s) from treasury controls.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Open</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $summary['open']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Closed</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $summary['closed']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">High/Critical</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $summary['critical']) }}</p>
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
                            <th class="px-4 py-3 text-left font-semibold">Exception</th>
                            <th class="px-4 py-3 text-left font-semibold">Priority / SLA</th>
                            <th class="px-4 py-3 text-left font-semibold">Line</th>
                            <th class="px-4 py-3 text-left font-semibold">Details</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Flow Agent</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($exceptions as $exception)
                            @php
                                $statusClass = match ((string) $exception->exception_status) {
                                    'open' => 'bg-rose-100 text-rose-700',
                                    'resolved' => 'bg-emerald-100 text-emerald-700',
                                    'waived' => 'bg-amber-100 text-amber-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };

                                $priorityBand = (string) ($exception->priority_band ?? 'low');
                                $priorityClass = match ($priorityBand) {
                                    'urgent' => 'bg-red-100 text-red-700',
                                    'high' => 'bg-rose-100 text-rose-700',
                                    'medium' => 'bg-amber-100 text-amber-700',
                                    'closed' => 'bg-slate-100 text-slate-600',
                                    default => 'bg-emerald-100 text-emerald-700',
                                };

                                $ageHours = (int) ($exception->age_hours ?? 0);
                                $slaHoursForRow = (int) ($exception->sla_hours ?? $slaHours);
                                $slaBreached = (bool) ($exception->sla_breached ?? false);
                                $slaRemaining = max(0, $slaHoursForRow - $ageHours);
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="font-medium text-slate-800">{{ strtoupper((string) $exception->exception_code) }}</p>
                                    <p class="text-xs text-slate-500">{{ ucfirst((string) $exception->severity) }} | {{ ucfirst(str_replace('_', ' ', (string) $exception->match_stream)) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClass }}">{{ ucfirst($priorityBand) }}</span>
                                        @if ((string) $exception->exception_status === 'open')
                                            @if ($slaBreached)
                                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">SLA Breached</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">SLA due in {{ $slaRemaining }}h</span>
                                            @endif
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Age {{ $ageHours }}h</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ $exception->line?->line_reference ?: '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ optional($exception->line?->posted_at)->format('M d, Y') }} | {{ strtoupper((string) ($exception->line?->currency_code ?: 'NGN')) }} {{ number_format((int) ($exception->line?->amount ?? 0)) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ $exception->details ?: '-' }}</p>
                                    <p class="text-xs text-slate-500">Next: {{ $exception->next_action ?: '-' }}</p>
                                    @php
                                        $executionMetadata = (array) ($exception->metadata ?? []);
                                        $incidentId = (string) data_get($executionMetadata, 'execution_incident_id', '');
                                        $billingAttemptId = (int) data_get($executionMetadata, 'billing_attempt_id', 0);
                                        $payoutAttemptId = (int) data_get($executionMetadata, 'payout_attempt_id', 0);
                                        $webhookEventId = (int) data_get($executionMetadata, 'execution_webhook_event_id', 0);

                                        $contextQuery = [];
                                        $contextLabel = 'Open Execution Health';

                                        if ($billingAttemptId > 0) {
                                            $contextQuery = [
                                                'focus_pipeline' => 'billing',
                                                'billing_attempt_id' => $billingAttemptId,
                                                'incident_id' => $incidentId,
                                            ];
                                            $contextLabel = 'Open Billing Context';
                                        } elseif ($payoutAttemptId > 0) {
                                            $contextQuery = [
                                                'focus_pipeline' => 'payout',
                                                'payout_attempt_id' => $payoutAttemptId,
                                                'incident_id' => $incidentId,
                                            ];
                                            $contextLabel = 'Open Payout Context';
                                        } elseif ($webhookEventId > 0) {
                                            $contextQuery = [
                                                'focus_pipeline' => 'webhook',
                                                'webhook_event_id' => $webhookEventId,
                                                'incident_id' => $incidentId,
                                            ];
                                            $contextLabel = 'Open Webhook Context';
                                        }

                                        $hasContextLink = ($incidentId !== '') || ($contextQuery !== []);
                                    @endphp

                                    @if ($hasContextLink)
                                        <p class="text-xs text-slate-500">
                                            @if ($incidentId !== '')
                                                Incident {{ $incidentId }}
                                            @else
                                                Linked execution record
                                            @endif
                                            <a href="{{ route('execution.health', $contextQuery) }}" class="ml-1 font-semibold text-slate-700 hover:text-slate-900">{{ $contextLabel }}</a>
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst((string) $exception->exception_status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @php
                                        $insight = $flowAgentInsights[(int) $exception->id] ?? null;
                                    @endphp
                                    @if (is_array($insight))
                                        @php
                                            $riskClass = match ((string) ($insight['risk_level'] ?? 'low')) {
                                                'high' => 'bg-red-100 text-red-700',
                                                'medium' => 'bg-amber-100 text-amber-700',
                                                default => 'bg-emerald-100 text-emerald-700',
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $riskClass }}">
                                            {{ ucfirst((string) ($insight['risk_level'] ?? 'low')) }} risk
                                        </span>
                                        <p class="mt-1 text-xs text-slate-700">{{ (string) ($insight['suggested_match'] ?? '-') }}</p>
                                        <p class="mt-1 text-[11px] text-slate-500">Confidence {{ (int) ($insight['confidence'] ?? 0) }}% | {{ (string) ($insight['generated_at'] ?? '-') }}</p>
                                        <p class="mt-1 text-[11px] text-slate-500">Next: {{ (string) ($insight['next_action'] ?? '-') }}</p>
                                    @elseif (! $flowAgentsEnabled)
                                        <span class="text-xs text-slate-400">AI disabled for tenant</span>
                                    @else
                                        <span class="text-xs text-slate-400">Not analyzed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if (($canOperate && (string) $exception->exception_status === 'open') || $flowAgentsEnabled)
                                        <div class="inline-flex items-center gap-2">
                                            @if ($flowAgentsEnabled)
                                                <button
                                                    type="button"
                                                    wire:click="analyzeExceptionWithFlowAgent({{ (int) $exception->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="analyzeExceptionWithFlowAgent({{ (int) $exception->id }})"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M12 3v3"></path>
                                                        <path d="M12 18v3"></path>
                                                        <path d="M3 12h3"></path>
                                                        <path d="M18 12h3"></path>
                                                        <path d="M6.3 6.3l2.1 2.1"></path>
                                                        <path d="M15.6 15.6l2.1 2.1"></path>
                                                        <path d="M17.7 6.3l-2.1 2.1"></path>
                                                        <path d="M8.4 15.6l-2.1 2.1"></path>
                                                    </svg>
                                                    <span wire:loading.remove wire:target="analyzeExceptionWithFlowAgent({{ (int) $exception->id }})">Use Flow Agent</span>
                                                    <span wire:loading wire:target="analyzeExceptionWithFlowAgent({{ (int) $exception->id }})">Analyzing...</span>
                                                </button>
                                            @endif
                                            @if ($canOperate && (string) $exception->exception_status === 'open')
                                                <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'resolved')" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Resolve</button>
                                                <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'waived')" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Waive</button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500">Closed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No reconciliation exceptions found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Showing {{ $exceptions->firstItem() ?? 0 }}-{{ $exceptions->lastItem() ?? 0 }} of {{ $exceptions->total() }}</p>
                    {{ $exceptions->links() }}
                </div>
            </div>
        @endif
    </div>

    @if ($showResolutionModal)
        <div wire:click="closeResolutionModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-8">
                <div wire:click.stop class="fd-card w-full max-w-xl p-6">
                    <h3 class="text-base font-semibold text-slate-900">{{ $resolutionAction === 'waived' ? 'Waive Treasury Exception' : 'Resolve Treasury Exception' }}</h3>
                    <p class="mt-1 text-sm text-slate-600">Capture a note for audit and handoff clarity.</p>

                    <label class="mt-4 block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Resolution Note</span>
                        <textarea wire:model.defer="resolutionNotes" rows="4" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="What was validated and why is this closed?"></textarea>
                        @error('resolutionNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" wire:click="closeResolutionModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" wire:click="applyResolution" wire:loading.attr="disabled" wire:target="applyResolution" class="rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                            <span wire:loading.remove wire:target="applyResolution">Save</span>
                            <span wire:loading wire:target="applyResolution">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


