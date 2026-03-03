<div class="space-y-6">
<div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="approval-timing-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif

        @if ($feedbackError)
            <div
                wire:key="approval-timing-feedback-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 4200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Approval Response Deadline Controls
            </span>
            <h2 class="mt-2 text-base font-semibold text-slate-900">Organization Defaults</h2>
            <p class="mt-1 text-sm text-slate-600">
                Set the default response timing for approval steps, when reminders fire, and when escalation starts.
            </p>
        </div>

        <form wire:submit.prevent="saveOrganizationDefaults" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Due Hours</span>
                    <input
                        type="number"
                        min="1"
                        max="720"
                        wire:model.defer="org_step_due_hours"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    >
                    @error('org_step_due_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Reminder Hours</span>
                    <input
                        type="number"
                        min="0"
                        wire:model.defer="org_reminder_hours_before_due"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    >
                    @error('org_reminder_hours_before_due')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Escalation Grace</span>
                    <input
                        type="number"
                        min="0"
                        max="720"
                        wire:model.defer="org_escalation_grace_hours"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    >
                    @error('org_escalation_grace_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
            </div>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                <p class="font-semibold">How this is applied</p>
                <p class="mt-1">
                    When an approval step becomes pending, Flowdesk sets its response timing using these values.
                    Reminder and escalation times are calculated from that step deadline.
                </p>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="saveOrganizationDefaults"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="saveOrganizationDefaults">Save Approval Timing Defaults</span>
                    <span wire:loading wire:target="saveOrganizationDefaults">Saving...</span>
                </button>
            </div>
        </form>
    </div>

    <div class="fd-card p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Department Overrides</h2>
                <p class="mt-1 text-sm text-slate-600">Departments listed here use their own approval response timings. Others inherit organization defaults.</p>
            </div>
            <button
                type="button"
                wire:click="openCreateOverrideModal"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
            >
                + Add Department Override
            </button>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Department</th>
                        <th class="px-4 py-3 text-left font-semibold">Due Hours</th>
                        <th class="px-4 py-3 text-left font-semibold">Reminder Before Due</th>
                        <th class="px-4 py-3 text-left font-semibold">Escalation Grace</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($overrides as $override)
                        <tr wire:key="timing-override-{{ $override->id }}">
                            <td class="px-4 py-3 text-slate-700">{{ $override->department?->name ?? ('Department #'.$override->department_id) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ (int) $override->step_due_hours }}h</td>
                            <td class="px-4 py-3 text-slate-700">{{ (int) $override->reminder_hours_before_due }}h</td>
                            <td class="px-4 py-3 text-slate-700">{{ (int) $override->escalation_grace_hours }}h</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="openEditOverrideModal({{ (int) $override->department_id }})"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeDepartmentOverride({{ (int) $override->department_id }})"
                                        wire:confirm="Remove this department timing override? It will inherit organization defaults."
                                        class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                No department overrides yet. All departments currently inherit organization defaults.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fd-card p-6">
        <h2 class="text-base font-semibold text-slate-900">Effective Timing Preview (Organization Default)</h2>
        <div class="mt-3 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Step Due</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) $orgEffective['step_due_hours'] }}h</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Reminder Trigger</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) $orgEffective['reminder_hours_before_due'] }}h before due</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Escalation Trigger</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) $orgEffective['escalation_grace_hours'] }}h after due</p>
            </div>
        </div>
    </div>

    @if ($showOverrideModal)
        <div
            x-data="{ open: @entangle('showOverrideModal').live }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[80] flex items-start justify-center bg-slate-900/50 px-4 py-10 sm:py-12"
            style="display: none;"
            @click.self="$wire.closeOverrideModal()"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                            Department Timing Override
                        </span>
                        <h3 class="mt-2 text-base font-semibold text-slate-900">
                            {{ $editingDepartmentId ? 'Edit Department Override' : 'Add Department Override' }}
                        </h3>
                    </div>
                    <button type="button" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100" wire:click="closeOverrideModal">Ã¢Å“â€¢</button>
                </div>

                <form wire:submit.prevent="saveDepartmentOverride" class="space-y-4 p-5">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Department</span>
                        <select
                            wire:model.defer="overrideForm.department_id"
                            @disabled($editingDepartmentId !== null)
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:cursor-not-allowed disabled:bg-slate-100"
                        >
                            <option value="">Select department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('overrideForm.department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Due Hours</span>
                            <input type="number" min="1" max="720" wire:model.defer="overrideForm.step_due_hours" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('overrideForm.step_due_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Reminder Hours</span>
                            <input type="number" min="0" wire:model.defer="overrideForm.reminder_hours_before_due" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('overrideForm.reminder_hours_before_due')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Escalation Grace</span>
                            <input type="number" min="0" max="720" wire:model.defer="overrideForm.escalation_grace_hours" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('overrideForm.escalation_grace_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                        <button type="button" wire:click="closeOverrideModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveDepartmentOverride" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                            <span wire:loading.remove wire:target="saveDepartmentOverride">Save Override</span>
                            <span wire:loading wire:target="saveDepartmentOverride">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
