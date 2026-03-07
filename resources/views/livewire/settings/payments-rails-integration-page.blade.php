<div class="space-y-6">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="payments-rails-feedback-{{ $feedbackKey }}"
            class="pointer-events-none fixed z-[95]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg {{ $feedbackError ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                {{ $feedbackError ?: $feedbackMessage }}
            </div>
        </div>
    @endif

    @php
        $statusBadgeClass = match ((string) ($status['tone'] ?? 'slate')) {
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };

        $statusCardClass = match ((string) ($status['tone'] ?? 'slate')) {
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-900',
            default => 'border-slate-200 bg-slate-50 text-slate-900',
        };

        $testStatusClass = match ((string) $lastTestStatus) {
            'passed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };
    @endphp

    <section class="fd-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                    Payments Rails Integration
                </span>
                <h2 class="mt-2 text-base font-semibold text-slate-900">Payments Rail Controls</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Connect your payout rail, run quick connection checks, and pause or resume when needed.
                </p>
            </div>

            <a
                href="{{ route('settings.index') }}"
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
            >
                Back to Settings
            </a>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
            <div class="grid gap-3 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Provider</span>
                    <select wire:model.defer="connectForm.provider_key" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Select provider</option>
                        @foreach ($providerOptions as $provider)
                            <option value="{{ $provider }}">{{ $provider }}</option>
                        @endforeach
                    </select>
                    @error('connectForm.provider_key')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </label>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    <p class="font-semibold text-slate-800">Current status</p>
                    <p class="mt-1">{{ $status['description'] }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <button
                    type="button"
                    wire:click="connect"
                    wire:loading.attr="disabled"
                    wire:target="connect"
                    class="rounded-xl border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="connect">Connect</span>
                    <span wire:loading wire:target="connect">Connecting...</span>
                </button>
                <button
                    type="button"
                    wire:click="testConnection"
                    wire:loading.attr="disabled"
                    wire:target="testConnection"
                    class="rounded-xl border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="testConnection">Test Connection</span>
                    <span wire:loading wire:target="testConnection">Testing...</span>
                </button>
                <button
                    type="button"
                    wire:click="syncNow"
                    wire:loading.attr="disabled"
                    wire:target="syncNow"
                    class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="syncNow">Sync Now</span>
                    <span wire:loading wire:target="syncNow">Syncing...</span>
                </button>
                <button
                    type="button"
                    wire:click="togglePause"
                    wire:loading.attr="disabled"
                    wire:target="togglePause"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold disabled:opacity-70 {{ $isPaused ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' }}"
                >
                    <span wire:loading.remove wire:target="togglePause">{{ $isPaused ? 'Resume' : 'Pause' }}</span>
                    <span wire:loading wire:target="togglePause">Updating...</span>
                </button>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-5">
        <article class="rounded-2xl border p-5 {{ $statusCardClass }}">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Connection Status</p>
            <p class="mt-2 text-base font-semibold">{{ $status['label'] }}</p>
            <span class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $statusBadgeClass }}">{{ $status['label'] }}</span>
        </article>

        <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Execution Mode</p>
            <p class="mt-2 text-base font-semibold">{{ str_replace('_', ' ', $executionMode) }}</p>
        </article>

        <article class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Provider</p>
            <p class="mt-2 text-base font-semibold">{{ $providerKey }}</p>
        </article>

        <article class="rounded-2xl border border-cyan-200 bg-cyan-50 p-5 text-cyan-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">Last Connection Test</p>
            <p class="mt-2 text-sm font-semibold">{{ $lastTestedAt }}</p>
            <span class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $testStatusClass }}">{{ $lastTestStatus === 'not_run' ? 'Not run' : $lastTestStatus }}</span>
        </article>

        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Last Sync</p>
            <p class="mt-2 text-sm font-semibold">{{ $lastSyncedAt }}</p>
        </article>
    </section>

    @if ($lastTestMessage !== '')
        <section class="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-600">
            <p class="font-semibold text-slate-800">Last test note</p>
            <p class="mt-1">{{ $lastTestMessage }}</p>
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Recent Payments Rail Actions</h3>
            <p class="text-xs text-slate-500">10 per page</p>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-[0.08em] text-slate-500">
                        <th class="px-3 py-2">Time</th>
                        <th class="px-3 py-2">Action</th>
                        <th class="px-3 py-2">Performed By</th>
                        <th class="px-3 py-2">Result</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($recentActions as $row)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $row['time'] }}</td>
                            <td class="px-3 py-2">{{ $row['action'] }}</td>
                            <td class="px-3 py-2">{{ $row['actor'] }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $row['result'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No actions recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-3 py-3">
            <p class="text-xs text-slate-500">
                Showing {{ $recentActions->count() }} of {{ $recentActions->total() }} actions
            </p>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="previousPage('railActionPage')"
                    @disabled($recentActions->onFirstPage())
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Prev
                </button>
                <span class="text-xs font-medium text-slate-600">
                    Page {{ $recentActions->currentPage() }} of {{ max($recentActions->lastPage(), 1) }}
                </span>
                <button
                    type="button"
                    wire:click="nextPage('railActionPage')"
                    @disabled(! $recentActions->hasMorePages())
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Next
                </button>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
        <p class="font-semibold text-slate-900">Usage note</p>
        <p class="mt-1 text-xs text-slate-600">
            These controls are for your organization's payout rail status and routine checks.
        </p>
    </section>
</div>






