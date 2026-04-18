<div class="fd-card p-5">
    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Items That Need Attention</h3>
            <p class="text-xs text-slate-500">Fix or skip each item below. Open the full list to work through everything at once.</p>
            <p class="mt-1 text-xs text-slate-500">Who can act: {{ implode(', ', (array) $exceptionActionAllowedRoles) }}.</p>
            @if ($makerCheckerRequired)
                <p class="text-xs text-amber-700">A second person must confirm your decision — you cannot close an item you raised.</p>
            @endif
            @if ($flowAgentsEnabled)
                <div class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                    <span class="font-semibold">Flow Agent:</span> tap <span class="font-semibold">Suggest a Match</span> to get an AI recommendation for each item.
                    @if ($flowAgentsAdvisoryOnly)
                        It recommends only — your team makes the final call.
                    @endif
                </div>
            @endif
        </div>
        <a href="{{ route('treasury.reconciliation-exceptions') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">See All Unresolved Items<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Item</th>
                    <th class="px-3 py-2 text-left font-semibold">Bank Line</th>
                    <th class="px-3 py-2 text-left font-semibold">What to Do</th>
                    <th class="px-3 py-2 text-left font-semibold">Created</th>
                    <th class="px-3 py-2 text-left font-semibold">AI Suggestion</th>
                    <th class="px-3 py-2 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($openExceptionsPreview as $exception)
                    @php
                        $severityClass = match ((string) $exception->severity) {
                            'critical' => 'bg-red-100 text-red-700',
                            'high' => 'bg-rose-100 text-rose-700',
                            'medium' => 'bg-amber-100 text-amber-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-3 text-slate-600">
                            <p class="font-medium text-slate-800">{{ strtoupper((string) $exception->exception_code) }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $exception->match_stream)) }}</p>
                            <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $severityClass }}">{{ ucfirst((string) $exception->severity) }}</span>
                        </td>
                        <td class="px-3 py-3 text-slate-600">
                            <p>{{ $exception->line?->line_reference ?: '-' }}</p>
                            <p class="text-xs text-slate-500">{{ strtoupper((string) ($exception->line?->currency_code ?: 'NGN')) }} {{ number_format((int) ($exception->line?->amount ?? 0)) }}</p>
                        </td>
                        <td class="px-3 py-3 text-slate-600">{{ $exception->next_action ?: '-' }}</td>
                        <td class="px-3 py-3 text-slate-600">{{ optional($exception->created_at)->format('M d, Y H:i') }}</td>
                        <td class="px-3 py-3 text-slate-600">
                            @php $insight = $flowAgentInsights[(int) $exception->id] ?? null; @endphp
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
                                <p class="mt-1 text-[11px] text-slate-500">Confidence {{ (int) ($insight['confidence'] ?? 0) }}%</p>
                            @elseif (! $flowAgentsEnabled)
                                <span class="text-xs text-slate-400">AI not enabled</span>
                            @else
                                <span class="text-xs text-slate-400">Not reviewed yet</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right">
                            @if ($canResolveExceptions || $flowAgentsEnabled)
                                <div class="inline-flex items-center gap-2">
                                    @if ($canResolveExceptions)
                                        <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'resolved')" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Mark as Fixed</button>
                                        <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'waived')" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Accept & Close</button>
                                    @endif
                                    @if ($flowAgentsEnabled)
                                        <button
                                            type="button"
                                            wire:click="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M12 3v3"></path><path d="M12 18v3"></path>
                                                <path d="M3 12h3"></path><path d="M18 12h3"></path>
                                                <path d="M6.3 6.3l2.1 2.1"></path><path d="M15.6 15.6l2.1 2.1"></path>
                                                <path d="M17.7 6.3l-2.1 2.1"></path><path d="M8.4 15.6l-2.1 2.1"></path>
                                            </svg>
                                            <span wire:loading.remove wire:target="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})">Suggest a Match</span>
                                            <span wire:loading wire:target="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})">Analyzing...</span>
                                        </button>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-500">View only</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">No unresolved items for this statement. Everything is matched.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
