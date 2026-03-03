<div class="space-y-6">
<div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="vendor-controls-feedback-success-{{ $feedbackKey }}"
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
                    Vendor Permission Matrix
                </span>
                <h2 class="mt-2 text-base font-semibold text-slate-900">Per-Action Role Access for Vendor Operations</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Choose exactly which roles can run each vendor action. This controls directory, finance, exports, and communication operations.
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
            @foreach ($actionDefinitions as $action => $label)
                <section class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $label }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Allowed Roles</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($roles as $role)
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        value="{{ $role }}"
                                        wire:model.defer="actionPolicies.{{ $action }}.allowed_roles"
                                        class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                                    >
                                    {{ ucfirst($role) }}
                                </label>
                            @endforeach
                        </div>
                        @error("actionPolicies.$action.allowed_roles")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </section>
            @endforeach

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="save">Save Vendor Controls</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>

