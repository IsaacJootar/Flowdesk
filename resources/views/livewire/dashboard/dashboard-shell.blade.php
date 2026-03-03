<div wire:init="loadMetrics" class="space-y-6">
    <section class="fd-card p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $roleTitle }}</p>
        <p class="mt-1 text-sm text-slate-600">{{ $roleDescription }}</p>
    </section>

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
            @php
                $metricCardStyles = [
                    'background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: #ffffff;',
                    'background: linear-gradient(135deg, #0f766e 0%, #059669 100%); color: #ffffff;',
                    'background: linear-gradient(135deg, #d97706 0%, #ea580c 100%); color: #ffffff;',
                    'background: linear-gradient(135deg, #7c3aed 0%, #4338ca 100%); color: #ffffff;',
                    'background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #ffffff;',
                    'background: linear-gradient(135deg, #be123c 0%, #e11d48 100%); color: #ffffff;',
                ];
            @endphp
            @foreach ($metrics as $metric)
                <div class="rounded-2xl p-5 shadow-sm" style="{{ $metricCardStyles[$loop->index % count($metricCardStyles)] }}">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: rgba(255,255,255,0.82);">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $metric['value'] }}</p>
                    @if (! empty($metric['words']))
                        <p class="mt-1 text-[11px] leading-tight" style="color: rgba(255,255,255,0.75);">{{ $metric['words'] }}</p>
                    @endif
                    <p class="mt-2 text-xs" style="color: rgba(255,255,255,0.82);">{{ $metric['hint'] }}</p>
                </div>
            @endforeach
        @endif
    </section>

    @if ($readyToLoad)
        @if ($roleSummaryCards !== [])
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($roleSummaryCards as $card)
                    @php
                        $tone = (string) ($card['tone'] ?? 'slate');
                        $toneClasses = match ($tone) {
                            'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
                            'sky' => 'border-sky-200 bg-sky-50 text-sky-900',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'violet' => 'border-violet-200 bg-violet-50 text-violet-900',
                            default => 'border-slate-200 bg-slate-50 text-slate-900',
                        };
                    @endphp
                    <div class="rounded-2xl border p-4 {{ $toneClasses }}">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold">{{ $card['value'] }}</p>
                        <p class="mt-2 text-xs">{{ $card['hint'] }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="fd-card p-6">
                <h2 class="text-sm font-semibold text-slate-900">Priority Actions</h2>
                <p class="mt-1 text-xs text-slate-500">Recommended next steps for your role context.</p>

                @if ($priorityActions === [])
                    <p class="mt-3 text-sm text-slate-500">No role actions available for currently enabled modules.</p>
                @else
                    <div class="mt-3 space-y-2">
                        @foreach ($priorityActions as $action)
                            @php
                                $actionToneClasses = [
                                    'border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-900',
                                    'border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-900',
                                    'border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-900',
                                    'border-violet-200 bg-violet-50 hover:bg-violet-100 text-violet-900',
                                    'border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-900',
                                    'border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-900',
                                ];
                                $actionTone = $actionToneClasses[$loop->index % count($actionToneClasses)];
                            @endphp
                            <a href="{{ $action['url'] }}" class="block rounded-xl border px-3 py-2 transition-colors {{ $actionTone }}">
                                <p class="text-sm font-semibold">{{ $action['label'] }}</p>
                                <p class="mt-0.5 text-xs opacity-80">{{ $action['hint'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="fd-card p-6">
                <h2 class="text-sm font-semibold text-slate-900">Recent Control Signals</h2>
                <p class="mt-1 text-xs text-slate-500">Latest events relevant to this dashboard lens.</p>

                @if ($recentSignals === [])
                    <p class="mt-3 text-sm text-slate-500">No recent signals yet.</p>
                @else
                    <div class="mt-3 divide-y divide-slate-100">
                        @foreach ($recentSignals as $signal)
                            <div class="py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-800">{{ $signal['label'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $signal['time'] }}</p>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $signal['detail'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>

