<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="procurement-exceptions"
        title="Purchase Order Mismatches"
        description="These are cases where a supplier invoice does not match the purchase order or goods receipt. Each mismatch must be resolved before the payment can be released."
        :bullets="[
            'Common causes: wrong price, short delivery, or invoice from the wrong supplier.',
            'You can accept the difference (approve), reject the invoice, or request a credit note.',
            'Resolved mismatches are logged for audit and supplier performance tracking.',
        ]"
    />
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div wire:key="procurement-match-feedback-success-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3200)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                {{ $feedbackMessage }}
            </div>
        @endif
        @if ($feedbackError)
            <div wire:key="procurement-match-feedback-error-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg">
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Procurement Match Issues</h2>
            <p class="text-xs text-slate-500">Review 3-way match failures and apply controlled resolution actions.</p>
            <p class="mt-1 text-xs text-slate-500">Action roles: {{ implode(', ', (array) $matchActionAllowedRoles) }}.</p>
            @if ($flowAgentsEnabled)
                <p class="mt-1 text-xs text-sky-700">
                    <span class="font-semibold">Flow Agent:</span> use <span class="font-semibold">Use Flow Agent</span> for `why blocked` and `next action` guidance.
                    @if ($flowAgentsAdvisoryOnly)
                        Advisory mode is active.
                    @endif
                </p>
            @endif
            @if ($makerCheckerRequired)
                <p class="text-xs text-amber-700">Maker-checker is enabled: the user who generated the mismatch cannot close it.</p>
            @endif
        </div>
        <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
            <span aria-hidden="true">&larr;</span>
            <span>Back to Release Desk</span>
        </a>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-5">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="PO number, invoice number, issue code">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
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

            <div class="flex items-end justify-end">
                <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span>Rows</span>
                    <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Open Issues</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $summary['open']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Closed Issues</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format((int) $summary['resolved']) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-amber-700">High/Critical</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format((int) $summary['high']) }}</p>
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
                            <th class="px-4 py-3 text-left font-semibold">Issue</th>
                            <th class="px-4 py-3 text-left font-semibold">PO / Invoice</th>
                            <th class="px-4 py-3 text-left font-semibold">Details</th>
                            <th class="px-4 py-3 text-left font-semibold">Match</th>
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
                                $insight = $flowAgentInsights[(int) $exception->id] ?? null;
                                $riskLevel = strtolower((string) ($insight['risk_level'] ?? ''));
                                $riskClass = match ($riskLevel) {
                                    'high' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'medium' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    'low' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    default => 'border-slate-200 bg-slate-50 text-slate-600',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ strtoupper((string) $exception->exception_code) }}</p>
                                    <p class="text-xs text-slate-500">Severity: {{ ucfirst((string) $exception->severity) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>PO: {{ $exception->order?->po_number ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">Invoice: {{ $exception->invoice?->invoice_number ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ $exception->details ?: '-' }}</p>
                                    <p class="text-xs text-slate-500">Next: {{ (string) data_get((array) $exception->metadata, 'next_action', '-') }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>Status: {{ ucfirst((string) ($exception->matchResult?->match_status ?? 'pending')) }}</p>
                                    <p class="text-xs text-slate-500">Score: {{ number_format((float) ($exception->matchResult?->match_score ?? 0), 2) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $exception->exception_status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if (is_array($insight))
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $riskClass }}">
                                            {{ ucfirst((string) ($insight['risk_level'] ?? 'low')) }} risk
                                        </span>
                                        <p class="mt-1 text-xs text-slate-600">{{ (string) ($insight['why_blocked'] ?? '-') }}</p>
                                        <p class="mt-1 text-[11px] text-slate-500">Next: {{ (string) ($insight['next_action'] ?? '-') }}</p>
                                    @elseif (! $flowAgentsEnabled)
                                        <span class="text-xs text-slate-400">AI disabled for organization</span>
                                    @else
                                        <span class="text-xs text-slate-400">Not analyzed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ((string) $exception->exception_status === 'open' || $flowAgentsEnabled)
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
                                            @if ((string) $exception->exception_status === 'open')
                                                <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'resolved')" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Resolve</button>
                                                <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'waived')" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Waive</button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500">Closed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No procurement match issues found for the selected filters.</td>
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
                    <h3 class="text-base font-semibold text-slate-900">{{ $resolutionAction === 'waived' ? 'Waive Match Issue' : 'Resolve Match Issue' }}</h3>
                    <p class="mt-1 text-sm text-slate-600">Capture a clear reason for audit trail and future troubleshooting.</p>

                    <label class="mt-4 block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Resolution Note</span>
                        <textarea wire:model.defer="resolutionNotes" rows="4" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="What changed and why is this issue being closed?"></textarea>
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
