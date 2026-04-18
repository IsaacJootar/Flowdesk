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
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization Execution Mode</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">Choose Decision-only or Execution-enabled mode for this organization.</p>
        </div>
</div>

    @include('livewire.platform.partials.tenant-section-tabs', ['company' => $company, 'tenantContextRoute' => 'platform.tenants.execution-mode'])

    <form wire:submit.prevent="save" class="fd-card space-y-4 p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Execution Mode</span>
                <select wire:model.defer="modeForm.payment_execution_mode" class="w-full rounded-xl text-sm @error('modeForm.payment_execution_mode') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 @enderror">
                    @foreach ($modes as $mode)
                        <option value="{{ $mode }}">{{ $mode === 'execution_enabled' ? 'Execution-enabled' : 'Decision-only' }}</option>
                    @endforeach
                </select>
                @error('modeForm.payment_execution_mode')
                    <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Provider</span>
                <div class="space-y-2">
                    <select wire:model.defer="modeForm.execution_provider" class="w-full rounded-xl text-sm @error('modeForm.execution_provider') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 @enderror">
                        <option value="">Select execution provider</option>
                        @foreach ($providerOptions as $provider)
                            <option value="{{ $provider }}">{{ $provider }}</option>
                        @endforeach
                    </select>

                    @error('modeForm.execution_provider')
                        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="space-y-1">
                            <p class="text-slate-500">Supported keys:</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($providerHelperKeys as $providerKey)
                                    @php
                                        $badgeClass = match (strtolower((string) $providerKey)) {
                                            'manual_ops' => 'border-slate-300 bg-slate-100 text-slate-700',
                                            'paystack' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'flutterwave' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            default => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2 py-0.5 font-semibold {{ $badgeClass }}">{{ $providerKey }}</span>
                                @endforeach
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="useManualOperationsProvider"
                            wire:loading.attr="disabled"
                            wire:target="useManualOperationsProvider"
                            class="inline-flex h-8 items-center rounded-lg border border-slate-900 bg-slate-900 px-2.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="useManualOperationsProvider">Use manual_ops</span>
                            <span wire:loading wire:target="useManualOperationsProvider">Setting...</span>
                        </button>
                    </div>
                </div>
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





