<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="dept-feedback-success-{{ $feedbackKey }}"
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
                wire:key="dept-feedback-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
        <div class="fd-card p-6">
            <div class="mb-4">
                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                    Department Setup
                </span>
                <h2 class="mt-2 text-base font-semibold text-slate-900">Create Department</h2>
            </div>

            <form wire:submit.prevent="createDepartment" class="space-y-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department Name</span>
                    <input
                        type="text"
                        wire:model.defer="departmentForm.name"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="Operations"
                    >
                    @error('departmentForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Code</span>
                    <input
                        type="text"
                        wire:model.defer="departmentForm.code"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="OPS"
                    >
                    @error('departmentForm.code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department Head</span>
                    <select wire:model.defer="departmentForm.manager_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">No head assigned</option>
                        @foreach ($managerOptions as $managerOption)
                            <option value="{{ $managerOption->id }}">{{ $managerOption->name }} ({{ ucfirst((string) $managerOption->role) }})</option>
                        @endforeach
                    </select>
                    @error('departmentForm.manager_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="createDepartment"
                    class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="createDepartment">Add Department</span>
                    <span wire:loading wire:target="createDepartment">Creating...</span>
                </button>
            </form>
        </div>

        <div class="fd-card p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Departments</h3>
                    <p class="text-sm text-slate-600">Manage department head assignments and active structure.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model.live.debounce.350ms="search"
                        class="rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="Search name or code"
                    >
                    <select wire:model.live="perPage" class="rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Code</th>
                            <th class="px-4 py-3 text-left font-semibold">Head</th>
                            <th class="px-4 py-3 text-left font-semibold">Members</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($departments as $department)
                            <tr wire:key="department-row-{{ $department->id }}">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $department->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $department->code ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <select wire:model.defer="departmentManagers.{{ $department->id }}" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                        <option value="">No head assigned</option>
                                        @foreach ($managerOptions as $managerOption)
                                            <option value="{{ $managerOption->id }}">{{ $managerOption->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ number_format((int) $department->users_count) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="saveDepartmentManager({{ $department->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="saveDepartmentManager"
                                        class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                                    >
                                        <span wire:loading.remove wire:target="saveDepartmentManager">Save</span>
                                        <span wire:loading wire:target="saveDepartmentManager">Saving...</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                                    No departments found for this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $departments->firstItem() ?? 0 }}-{{ $departments->lastItem() ?? 0 }} of {{ $departments->total() }}
                </p>
                {{ $departments->links() }}
            </div>
        </div>
    </div>
</div>
