<div class="space-y-6">
    <x-module-explainer
        key="accounting-export"
        title="Accounting Export"
        description="Download completed Flowdesk spending as a CSV your accounting team can import."
        :bullets="[
            'Only ready accounting records are exported.',
            'Records with missing Chart of Accounts mapping are shown clearly and blocked from export.',
            'Exported records stay linked to the CSV batch for audit history.',
        ]"
    />

    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 340px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div wire:key="accounting-export-success-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3600)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                {{ $feedbackMessage }}
            </div>
        @endif
        @if ($feedbackError)
            <div wire:key="accounting-export-error-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 4800)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-lg">
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <section class="fd-card border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">&larr; Back to Reports</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Accounting Export</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Ready Records for Accounting</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">
                    Pick a date range, fix any missing account mapping, then export the ready records to CSV.
                </p>
            </div>

            <a href="{{ route('settings.chart-of-accounts') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                Chart of Accounts<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">From</span>
                <input type="date" wire:model.live="fromDate" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">To</span>
                <input type="date" wire:model.live="toDate" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
            <div class="rounded-xl border border-emerald-200 bg-white px-3 py-2">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">Ready</p>
                <p class="mt-1 text-xl font-semibold text-emerald-900">{{ number_format((int) $summary['ready']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-white px-3 py-2">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-amber-700">Needs Mapping</p>
                <p class="mt-1 text-xl font-semibold text-amber-900">{{ number_format((int) $summary['needs_mapping']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Exported</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format((int) $summary['exported']) }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">
                {{ (int) $summary['skipped'] }} skipped record(s) in this range are not included.
            </p>
            @if ($canExport)
                <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                    <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                    <span wire:loading wire:target="exportCsv">Exporting...</span>
                </button>
            @else
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-500">View-only access</span>
            @endif
        </div>
    </section>

    @if ($missingRows->isNotEmpty())
        <section class="fd-card border border-amber-200 bg-amber-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-amber-900">Fix Before Export</h3>
                    <p class="mt-1 text-xs text-amber-800">These records need a mapped Spend Type before the CSV can be created.</p>
                </div>
                <a href="{{ route('settings.chart-of-accounts') }}" class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">Fix Mapping<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-[0.12em] text-amber-700">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Record</th>
                            <th class="px-3 py-2">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($missingRows as $row)
                            <tr class="border-t border-amber-200">
                                <td class="px-3 py-2">{{ $row->event_date?->format('M d, Y') }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-slate-900">{{ $row->description }}</p>
                                    <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $row->source_type)) }} #{{ $row->source_id }}</p>
                                </td>
                                <td class="px-3 py-2 text-amber-800">{{ $row->last_error ?: 'Needs Chart of Accounts mapping.' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="grid gap-4 xl:grid-cols-2">
        <div class="fd-card border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">Ready to Export</h3>
            <p class="mt-1 text-xs text-slate-500">First 10 ready records in the selected range.</p>
            <div class="mt-3 space-y-2">
                @forelse ($readyRows as $row)
                    <div class="rounded-xl border border-slate-200 px-3 py-2">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $row->description }}</p>
                                <p class="text-xs text-slate-500">{{ $row->event_date?->format('M d, Y') }} &middot; {{ ucfirst(str_replace('_', ' ', (string) $row->source_type)) }} #{{ $row->source_id }}</p>
                            </div>
                            <p class="text-sm font-semibold text-slate-900">{{ number_format((int) $row->amount) }} {{ $row->currency_code }}</p>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">No ready records in this date range.</p>
                @endforelse
            </div>
        </div>

        <div class="fd-card border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">Recent CSV Exports</h3>
            <p class="mt-1 text-xs text-slate-500">Download previous batches without recreating them.</p>
            <div class="mt-3 space-y-2">
                @forelse ($batches as $batch)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $batch->from_date?->format('M d, Y') }} to {{ $batch->to_date?->format('M d, Y') }}</p>
                            <p class="text-xs text-slate-500">{{ number_format((int) $batch->row_count) }} row(s) &middot; {{ $batch->created_at?->format('M d, Y H:i') }}</p>
                        </div>
                        @if ($batch->file_path)
                            <a href="{{ route('reports.accounting-export.download', $batch) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Download<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                        @endif
                    </div>
                @empty
                    <p class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">No CSV exports yet.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
