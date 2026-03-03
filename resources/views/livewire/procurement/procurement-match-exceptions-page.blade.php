<div wire:init="loadData" class="space-y-5">
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
            <h2 class="text-base font-semibold text-slate-900">Procurement Match Exceptions</h2>
            <p class="text-xs text-slate-500">Review 3-way match failures and apply controlled resolution actions.</p>
        </div>
        <a href="{{ route('procurement.orders') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
            <span aria-hidden="true">&larr;</span>
            <span>Back to Procurement Orders</span>
        </a>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-5">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="PO number, invoice number, exception code">
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
            <p class="text-xs uppercase tracking-[0.1em] text-rose-700">Open Exceptions</p>
            <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format((int) $summary['open']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-[0.1em] text-emerald-700">Closed Exceptions</p>
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
                            <th class="px-4 py-3 text-left font-semibold">Exception</th>
                            <th class="px-4 py-3 text-left font-semibold">PO / Invoice</th>
                            <th class="px-4 py-3 text-left font-semibold">Details</th>
                            <th class="px-4 py-3 text-left font-semibold">Match</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
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
                                <td class="px-4 py-3 text-right">
                                    @if ((string) $exception->exception_status === 'open')
                                        <div class="inline-flex items-center gap-2">
                                            <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'resolved')" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Resolve</button>
                                            <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'waived')" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Waive</button>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500">Closed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No procurement match exceptions found for the selected filters.</td>
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
                    <h3 class="text-base font-semibold text-slate-900">{{ $resolutionAction === 'waived' ? 'Waive Match Exception' : 'Resolve Match Exception' }}</h3>
                    <p class="mt-1 text-sm text-slate-600">Capture a clear reason for audit trail and future troubleshooting.</p>

                    <label class="mt-4 block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Resolution Note</span>
                        <textarea wire:model.defer="resolutionNotes" rows="4" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="What changed and why is this exception being closed?"></textarea>
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