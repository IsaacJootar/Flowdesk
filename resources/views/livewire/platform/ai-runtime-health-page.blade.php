<div wire:init="loadData" class="space-y-5">
    @php
        $runtime = (array) ($snapshot['runtime'] ?? []);
        $checks = (array) ($snapshot['checks'] ?? []);
        $metrics = (array) ($snapshot['metrics'] ?? []);
        $lastModelSuccess = is_array($snapshot['last_model_success'] ?? null) ? $snapshot['last_model_success'] : null;
        $lastAnalysis = is_array($snapshot['last_analysis'] ?? null) ? $snapshot['last_analysis'] : null;
        $recent = is_array($snapshot['recent_analyses'] ?? null) ? $snapshot['recent_analyses'] : [];

        $ollamaReachable = $checks['ollama_reachable'] ?? null;
        $primaryLoaded = $checks['primary_model_loaded'] ?? null;
    @endphp

    <div class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Platform Operations</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">AI Runtime Health &amp; Capability Monitor</h2>
                <p class="mt-1 text-sm text-slate-600">Track model availability, OCR capabilities, and receipt-agent fallback trends across organization activity.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="refreshSnapshot" wire:loading.attr="disabled" wire:target="refreshSnapshot" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <span wire:loading.remove wire:target="refreshSnapshot">Refresh Snapshot</span>
                    <span wire:loading wire:target="refreshSnapshot">Refreshing...</span>
                </button>
                <a href="{{ route('platform.operations.execution') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Execution Operations</a>
                <a href="{{ route('platform.operations.hub') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Operations Hub</a>
            </div>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Runtime Provider</p>
            <p class="mt-2 text-sm font-semibold text-sky-900">{{ strtoupper((string) ($runtime['provider'] ?? 'unknown')) }}</p>
            <p class="mt-1 text-xs text-sky-800 break-all">{{ (string) ($runtime['base_url'] ?? '-') !== '' ? (string) ($runtime['base_url'] ?? '-') : '-' }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Primary Model</p>
            <p class="mt-2 text-sm font-semibold text-indigo-900">{{ (string) ($runtime['primary_model'] ?? '') !== '' ? (string) ($runtime['primary_model'] ?? '') : '-' }}</p>
            <p class="mt-1 text-xs text-indigo-800">
                @if ($primaryLoaded === null)
                    Model-load check not required for current provider.
                @elseif ($primaryLoaded)
                    Primary model is loaded.
                @else
                    Primary model is not loaded.
                @endif
            </p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Model Runtime</p>
            <p class="mt-2 text-sm font-semibold text-emerald-900">
                @if ($ollamaReachable === null)
                    Not using Ollama
                @elseif ($ollamaReachable)
                    Ollama reachable
                @else
                    Ollama unreachable
                @endif
            </p>
            <p class="mt-1 text-xs text-emerald-800">
                Loaded models:
                @if (($checks['loaded_models_count'] ?? null) !== null)
                    {{ number_format((int) ($checks['loaded_models_count'] ?? 0)) }}
                @else
                    -
                @endif
            </p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">OCR Capability</p>
            <p class="mt-2 text-sm font-semibold text-amber-900">
                Image OCR: {{ ($checks['image_ocr_available'] ?? false) ? 'available' : 'missing' }}
            </p>
            <p class="mt-1 text-xs text-amber-800">
                PDF text extraction: {{ ($checks['pdf_text_available'] ?? false) ? 'available' : 'missing' }}
            </p>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">Receipt Analyses ({{ (int) ($metrics['window_hours'] ?? 24) }}h)</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) ($metrics['analyses'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Model-Assisted Analyses</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) ($metrics['model_assisted'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-orange-700">Deterministic Analyses</p>
            <p class="mt-2 text-2xl font-semibold text-orange-900">{{ number_format((int) ($metrics['deterministic'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Fallback Rate ({{ (int) ($metrics['window_hours'] ?? 24) }}h)</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((float) ($metrics['fallback_rate_percent'] ?? 0), 1) }}%</p>
        </div>
    </section>

    @if (($metrics['sample_truncated'] ?? false) === true)
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Metrics were calculated from a capped sample window to keep monitor queries bounded.
        </section>
    @endif

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="fd-card p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Last Model-Assisted Success</p>
            @if ($lastModelSuccess)
                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $lastModelSuccess['time'] }} · {{ $lastModelSuccess['company'] }}</p>
                <p class="mt-1 text-sm text-slate-700">
                    Engine: {{ $lastModelSuccess['engine'] }} | Model: {{ $lastModelSuccess['model'] !== '' ? $lastModelSuccess['model'] : '-' }} | Confidence: {{ (int) ($lastModelSuccess['confidence'] ?? 0) }}%
                </p>
            @else
                <p class="mt-2 text-sm text-slate-500">No model-assisted receipt analysis logged yet.</p>
            @endif
        </div>

        <div class="fd-card p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Last Receipt Analysis</p>
            @if ($lastAnalysis)
                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $lastAnalysis['time'] }} · {{ $lastAnalysis['company'] }}</p>
                <p class="mt-1 text-sm text-slate-700">
                    Engine: {{ $lastAnalysis['engine'] }} | Model: {{ $lastAnalysis['model'] !== '' ? $lastAnalysis['model'] : '-' }} | Confidence: {{ (int) ($lastAnalysis['confidence'] ?? 0) }}% | Fallback:
                    {{ ($lastAnalysis['fallback_used'] ?? false) ? 'yes' : 'no' }}
                </p>
            @else
                <p class="mt-2 text-sm text-slate-500">No receipt analysis activity logged yet.</p>
            @endif
        </div>
    </section>

    <section class="fd-card p-4">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-slate-900">Recent Receipt Analyses</h3>
            <p class="text-xs text-slate-500">Latest cross-organization activity for receipt extraction engine decisions.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Time</th>
                        <th class="px-3 py-2">Organization</th>
                        <th class="px-3 py-2">Engine</th>
                        <th class="px-3 py-2">Model</th>
                        <th class="px-3 py-2">Confidence</th>
                        <th class="px-3 py-2">Fallback</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $row)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-slate-500">{{ (string) ($row['time'] ?? '-') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ (string) ($row['company'] ?? '-') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ (string) ($row['engine'] ?? '-') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ (string) ($row['model'] ?? '-') !== '' ? (string) ($row['model'] ?? '-') : '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format((int) ($row['confidence'] ?? 0)) }}%</td>
                            <td class="px-3 py-2 text-slate-700">{{ ($row['fallback_used'] ?? false) ? 'yes' : 'no' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No receipt analysis events yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
