<div class="space-y-6">
    <x-module-explainer
        key="accounting-integrations"
        title="Accounting Integrations"
        description="Track provider readiness for QuickBooks, Sage, and Xero."
        :bullets="[
            'CSV export works now and is the first accounting handoff path.',
            'Provider API sync will use the same Chart of Accounts mapping and accounting event queue.',
            'Provider records stay company-scoped and visible only to owner, finance, and auditor roles.',
        ]"
    />

    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 340px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div wire:key="accounting-integrations-success-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3400)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                {{ $feedbackMessage }}
            </div>
        @endif
    </div>

    <section class="fd-card border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">&larr; Back to Settings</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Accounting Integrations</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Provider Readiness</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">
                    Use CSV export today. QuickBooks, Sage, and Xero will connect here when their provider adapters are built.
                </p>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">CSV Mapping</p>
                <p class="mt-1 text-lg font-semibold text-emerald-900">{{ $csvMappedCount }} / {{ $totalCategories }} ready</p>
                <a href="{{ route('settings.chart-of-accounts') }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-800 hover:text-emerald-950">Open Chart of Accounts<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
        @foreach ($rows as $row)
            @php
                $status = (string) $row['status'];
                $statusClass = match ($status) {
                    'connected' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'expired' => 'border-amber-200 bg-amber-50 text-amber-700',
                    'disabled' => 'border-slate-200 bg-slate-100 text-slate-600',
                    default => 'border-slate-200 bg-white text-slate-600',
                };
            @endphp

            <article class="fd-card border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ $row['label'] }}</h3>
                        <p class="mt-1 text-xs text-slate-500">Provider API sync shell</p>
                    </div>
                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                </div>

                <div class="mt-4 grid gap-3 text-sm">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Mapped Accounts</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ number_format((int) $row['mapped_accounts']) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Provider Accounts</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ number_format((int) $row['provider_accounts']) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Failed Syncs</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ number_format((int) $row['failed_events']) }}</p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-500">Last sync: {{ $row['last_synced_at'] }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($canManage)
                        @if ($status === 'disabled')
                            <button type="button" wire:click="setStatus('{{ $row['key'] }}', 'disconnected')" wire:loading.attr="disabled" wire:target="setStatus('{{ $row['key'] }}', 'disconnected')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-70">Mark Available</button>
                        @else
                            <button type="button" wire:click="setStatus('{{ $row['key'] }}', 'disabled')" wire:loading.attr="disabled" wire:target="setStatus('{{ $row['key'] }}', 'disabled')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-70">Disable</button>
                        @endif
                    @else
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">View only</span>
                    @endif
                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">Connect later</span>
                </div>
            </article>
        @endforeach
    </section>
</div>
