<div class="space-y-5">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="tenant-exec-mode-feedback-{{ $feedbackKey }}"
            class="pointer-events-none fixed z-[90]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg {{ $feedbackError ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                {{ $feedbackError ?: $feedbackMessage }}
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Execution Mode</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">Choose Decision-only or Execution-enabled mode for this tenant.</p>
        </div>
        <a
            href="{{ route('platform.tenants') }}"
            class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
            <span aria-hidden="true">&larr;</span>
            <span>Back to Tenants</span>
        </a>
    </div>

    @include('livewire.platform.partials.tenant-section-tabs', ['company' => $company])

    <form wire:submit.prevent="save" class="fd-card space-y-4 p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Execution Mode</span>
                <select wire:model.defer="modeForm.payment_execution_mode" class="w-full rounded-xl border-slate-300 text-sm">
                    @foreach ($modes as $mode)
                        <option value="{{ $mode }}">{{ $mode === 'execution_enabled' ? 'Execution-enabled' : 'Decision-only' }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Provider</span>
                <input type="text" wire:model.defer="modeForm.execution_provider" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Required for execution-enabled mode">
            </label>
        </div>

        <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-xs text-violet-700">
            Decision-only is the default. Execution-enabled requires active lifecycle, current billing, Requests+Expenses enabled, and an active default Payment Authorization workflow.
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                <span wire:loading.remove wire:target="save">Save Execution Mode</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
</div>
