<div class="space-y-6">
<div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="treasury-controls-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif
    </div>

    <div class="fd-card p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                    Treasury Controls
                </span>
                <h2 class="mt-2 text-base font-semibold text-slate-900">Tenant Reconciliation Guardrails</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Configure statement import limits and auto-match tolerances for treasury operations.
                </p>
            </div>

            <button
                type="button"
                wire:click="resetToDefault"
                wire:loading.attr="disabled"
                wire:target="resetToDefault"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
            >
                <span wire:loading.remove wire:target="resetToDefault">Reset Defaults</span>
                <span wire:loading wire:target="resetToDefault">Resetting...</span>
            </button>
        </div>

        <form wire:submit.prevent="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Statement Import Max Rows</span>
                    <input type="number" min="100" max="200000" wire:model.defer="controlsForm.statement_import_max_rows" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('controlsForm.statement_import_max_rows')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Exception Alert Age (Hours)</span>
                    <input type="number" min="1" max="720" wire:model.defer="controlsForm.exception_alert_age_hours" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('controlsForm.exception_alert_age_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Auto-Match Date Window (Days)</span>
                    <input type="number" min="0" max="30" wire:model.defer="controlsForm.auto_match_date_window_days" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('controlsForm.auto_match_date_window_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Auto-Match Amount Tolerance</span>
                    <input type="number" min="0" max="1000000" wire:model.defer="controlsForm.auto_match_amount_tolerance" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <p class="mt-1 text-xs text-slate-500">Maximum absolute amount difference allowed for automatic matching.</p>
                    @error('controlsForm.auto_match_amount_tolerance')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Auto-Match Minimum Confidence (%)</span>
                    <input type="number" min="1" max="99" wire:model.defer="controlsForm.auto_match_min_confidence" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <p class="mt-1 text-xs text-slate-500">Auto-match only applies when the best candidate meets this confidence floor.</p>
                    @error('controlsForm.auto_match_min_confidence')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Direct Expense Text Similarity Threshold (%)</span>
                    <input type="number" min="0" max="100" wire:model.defer="controlsForm.direct_expense_text_similarity_threshold" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <p class="mt-1 text-xs text-slate-500">Minimum merchant/text similarity used to treat expense evidence as strong match context.</p>
                    @error('controlsForm.direct_expense_text_similarity_threshold')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.defer="controlsForm.out_of_pocket_requires_reimbursement_link" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                Out-of-pocket expenses require reimbursement linkage before final reconciliation
            </label>

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="save">Save Treasury Controls</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
