<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="procurement-controls-feedback-success-{{ $feedbackKey }}"
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
                    Procurement Controls
                </span>
                <h2 class="mt-2 text-base font-semibold text-slate-900">Tenant Procurement Guardrails</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Configure conversion scope, issuance/receiving roles, invoice-link controls, and receipt tolerance behavior.
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
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Conversion Statuses</span>
                    <input
                        type="text"
                        wire:model.defer="controlsForm.conversion_allowed_statuses"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="approved, approved_for_execution"
                    >
                    <p class="mt-1 text-xs text-slate-500">Comma-separated request statuses allowed for Convert to PO.</p>
                    @error('controlsForm.conversion_allowed_statuses')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Default Delivery Days</span>
                    <input
                        type="number"
                        min="1"
                        max="365"
                        wire:model.defer="controlsForm.default_expected_delivery_days"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    >
                    <p class="mt-1 text-xs text-slate-500">Applied to new PO drafts created from approved requests.</p>
                    @error('controlsForm.default_expected_delivery_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Issue Allowed Roles</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                value="{{ $role }}"
                                wire:model.defer="controlsForm.issue_allowed_roles"
                                class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                            >
                            {{ ucfirst($role) }}
                        </label>
                    @endforeach
                </div>
                @error('controlsForm.issue_allowed_roles')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Receipt Allowed Roles</p>
                <p class="mt-1 text-xs text-slate-500">Only these roles can record goods receipts on purchase orders.</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                value="{{ $role }}"
                                wire:model.defer="controlsForm.receipt_allowed_roles"
                                class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                            >
                            {{ ucfirst($role) }}
                        </label>
                    @endforeach
                </div>
                @error('controlsForm.receipt_allowed_roles')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Invoice Link Allowed Roles</p>
                <p class="mt-1 text-xs text-slate-500">Only these roles can bind vendor invoices to purchase orders.</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                value="{{ $role }}"
                                wire:model.defer="controlsForm.invoice_link_allowed_roles"
                                class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                            >
                            {{ ucfirst($role) }}
                        </label>
                    @endforeach
                </div>
                @error('controlsForm.invoice_link_allowed_roles')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.defer="controlsForm.require_vendor_on_conversion" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                    Require vendor before request can convert to PO
                </label>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.defer="controlsForm.auto_post_commitment_on_issue" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                    Auto-post budget commitment when PO is issued
                </label>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                    <input type="checkbox" wire:model.defer="controlsForm.allow_over_receipt" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                    Allow over-receipt quantities above PO ordered quantity (not recommended for strict control tenants)
                </label>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="save">Save Procurement Controls</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>