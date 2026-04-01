<div class="space-y-5">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="tenant-exec-policy-feedback-{{ $feedbackKey }}"
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
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization Execution Policy</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">Configure caps, checker limit, channels, and policy notes.</p>
        </div>
</div>

    @include('livewire.platform.partials.tenant-section-tabs', ['company' => $company, 'tenantContextRoute' => 'platform.tenants.execution-policy'])

    <form wire:submit.prevent="save" class="fd-card space-y-4 p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Max Transaction</span>
                <input type="number" min="0.01" step="0.01" wire:model.defer="policyForm.execution_max_transaction_amount" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Daily Cap</span>
                <input type="number" min="0.01" step="0.01" wire:model.defer="policyForm.execution_daily_cap_amount" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Monthly Cap</span>
                <input type="number" min="0.01" step="0.01" wire:model.defer="policyForm.execution_monthly_cap_amount" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Checker Limit</span>
                <input type="number" min="0.01" step="0.01" wire:model.defer="policyForm.execution_maker_checker_threshold_amount" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional">
            </label>
        </div>

        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Allowed Execution Channels</p>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($channels as $channel)
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" value="{{ $channel }}" wire:model.defer="policyForm.execution_allowed_channels" class="rounded border-slate-300">
                        <span>{{ str_replace('_', ' ', ucfirst($channel)) }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Policy Notes</span>
            <textarea rows="3" wire:model.defer="policyForm.execution_policy_notes" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional policy notes"></textarea>
        </label>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                <span wire:loading.remove wire:target="save">Save Execution Policy</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
</div>




