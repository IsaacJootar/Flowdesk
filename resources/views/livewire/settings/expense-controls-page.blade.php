<div class="space-y-6">
<div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="expense-controls-feedback-success-{{ $feedbackKey }}"
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
                    Expense Permission Matrix
                </span>
                <h2 class="mt-2 text-base font-semibold text-slate-900">Role, Department, and Threshold Controls</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Choose who can post expenses per action. Optional department and amount controls apply when set.
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
                        <p class="text-xs text-slate-500">
                            Allowed roles can perform this action. Optional controls can limit by department or amount.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Allowed Roles</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
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

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Department Scope (Optional)</p>
                            <select
                                multiple
                                wire:model.defer="actionPolicies.{{ $action }}.department_ids"
                                class="mt-2 h-28 w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                            >
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Leave blank to allow all departments.</p>
                            @error("actionPolicies.$action.department_ids")<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Amount Limit Per Role (Optional)</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                            @foreach ($roles as $role)
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium text-slate-600">{{ ucfirst($role) }} Limit</span>
                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        wire:model.defer="actionPolicies.{{ $action }}.amount_limits.{{ $role }}"
                                        placeholder="No cap"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                                    >
                                </label>
                            @endforeach
                        </div>
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                wire:model.defer="actionPolicies.{{ $action }}.require_secondary_approval_over_limit"
                                class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                            >
                            Require secondary approval when user amount cap is exceeded
                        </label>
                        @error("actionPolicies.$action.amount_limits")
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
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
                    <span wire:loading.remove wire:target="save">Save Expense Controls</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
