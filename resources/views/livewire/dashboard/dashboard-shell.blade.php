<div wire:init="loadMetrics" class="space-y-6">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @if (! $readyToLoad)
            @for ($i = 0; $i < 6; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-4 h-4 w-32 rounded bg-slate-200"></div>
                    <div class="mb-3 h-7 w-40 rounded bg-slate-200"></div>
                    <div class="h-3 w-24 rounded bg-slate-200"></div>
                </div>
            @endfor
        @else
            @php($metricCardStyles = [
                'background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: #ffffff;',
                'background: linear-gradient(135deg, #0f766e 0%, #059669 100%); color: #ffffff;',
                'background: linear-gradient(135deg, #d97706 0%, #ea580c 100%); color: #ffffff;',
                'background: linear-gradient(135deg, #7c3aed 0%, #4338ca 100%); color: #ffffff;',
                'background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #ffffff;',
                'background: linear-gradient(135deg, #be123c 0%, #e11d48 100%); color: #ffffff;',
            ])
            @foreach ($metrics as $metric)
                <div class="rounded-2xl p-5 shadow-sm" style="{{ $metricCardStyles[$loop->index % count($metricCardStyles)] }}">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: rgba(255,255,255,0.82);">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $metric['value'] }}</p>
                    <p class="mt-2 text-xs" style="color: rgba(255,255,255,0.82);">{{ $metric['hint'] }}</p>
                </div>
            @endforeach
        @endif
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <div class="fd-card p-6">
            <h2 class="text-sm font-semibold text-slate-900">Spend by Department</h2>
            <p class="mt-2 text-sm text-slate-500">Chart placeholder. Wire this panel when the Expenses and Budgets modules are implemented.</p>
        </div>

        <div class="fd-card p-6">
            <h2 class="text-sm font-semibold text-slate-900">Top Vendors</h2>
            <p class="mt-2 text-sm text-slate-500">Vendor insights placeholder. This shell is ready for lazy-loaded widgets.</p>
        </div>
    </section>
</div>
