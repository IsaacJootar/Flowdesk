<div wire:init="loadData" class="space-y-5">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="budget-feedback-success-{{ $feedbackKey }}"
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
                wire:key="budget-feedback-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl p-4 shadow-sm" style="background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: #ffffff;">
            <p class="text-xs uppercase tracking-[0.12em]" style="color: rgba(255,255,255,0.85);">Allocated (Active)</p>
            <p class="mt-2 text-xl font-semibold">NGN {{ number_format($summaryAllocated) }}</p>
        </div>
        <div class="rounded-2xl p-4 shadow-sm" style="background: linear-gradient(135deg, #0f766e 0%, #059669 100%); color: #ffffff;">
            <p class="text-xs uppercase tracking-[0.12em]" style="color: rgba(255,255,255,0.85);">Spent (Posted)</p>
            <p class="mt-2 text-xl font-semibold">NGN {{ number_format($summarySpent) }}</p>
        </div>
        <div class="rounded-2xl p-4 shadow-sm" style="background: linear-gradient(135deg, #d97706 0%, #ea580c 100%); color: #ffffff;">
            <p class="text-xs uppercase tracking-[0.12em]" style="color: rgba(255,255,255,0.85);">Remaining (Active)</p>
            <p class="mt-2 text-xl font-semibold" style="{{ $summaryRemaining < 0 ? 'color:#fee2e2;' : 'color:#ffffff;' }}">
                NGN {{ number_format($summaryRemaining) }}
            </p>
        </div>
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-4 xl:grid-cols-[1fr_auto] xl:items-end">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="Department name"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department</span>
                    <select wire:model.live="departmentFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                    <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Period Type</span>
                    <select wire:model.live="periodTypeFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All period types</option>
                        @foreach ($periodTypes as $periodType)
                            <option value="{{ $periodType }}">{{ ucfirst($periodType) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="inline-flex items-center gap-2">
                @if ($this->canManage)
                    <button
                        type="button"
                        wire:click="openCreateModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateModal"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="openCreateModal">New Budget</span>
                        <span wire:loading wire:target="openCreateModal">Opening...</span>
                    </button>
                @else
                    <p class="text-xs text-slate-500">Read-only access: owner or finance required for budget changes.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 6; $i++)
                    <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,departmentFilter,statusFilter,periodTypeFilter,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading budgets...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Period</th>
                            <th class="px-4 py-3 text-left font-semibold">Allocated</th>
                            <th class="px-4 py-3 text-left font-semibold">Spent</th>
                            <th class="px-4 py-3 text-left font-semibold">Remaining</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($budgets as $budget)
                            @php($metrics = $budgetMetrics[$budget->id] ?? ['spent' => 0, 'remaining' => (int) $budget->allocated_amount])
                            <tr wire:key="budget-{{ $budget->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $budget->department?->name ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ ucfirst($budget->period_type) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ optional($budget->period_start)->format('M d, Y') }} - {{ optional($budget->period_end)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">NGN {{ number_format((int) $budget->allocated_amount) }}</td>
                                <td class="px-4 py-3 text-slate-600">NGN {{ number_format((int) $metrics['spent']) }}</td>
                                <td class="px-4 py-3 {{ (int) $metrics['remaining'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                    NGN {{ number_format((int) $metrics['remaining']) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $budget->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ucfirst($budget->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($this->canManage)
                                        <div class="inline-flex items-center gap-2">
                                            <button type="button" wire:click="openEditModal({{ $budget->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
                                            @if ($budget->status === 'active')
                                                <button type="button" wire:click="closeBudget({{ $budget->id }})" wire:confirm="Close this budget?" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Close</button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No budgets found. @if ($this->canManage)Create your first department budget to activate budget guardrails.@endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $budgets->links() }}
            </div>
        @endif
    </div>

    @if ($showFormModal)
        <div class="fixed left-0 right-0 bottom-0 top-16 z-40 overflow-y-auto bg-slate-900/40 p-4">
            <div class="flex min-h-full items-start justify-center py-6">
                <div class="fd-card w-full max-w-2xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $isEditing ? 'Edit Budget' : 'Create Budget' }}</h2>
                            <p class="text-sm text-slate-500">Define department budget period and allocation.</p>
                        </div>
                        <button type="button" wire:click="closeFormModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="save" class="space-y-4">
                        @error('form.no_changes')
                            <div class="rounded-xl px-4 py-3 text-sm" style="background:#fffbeb;border:1px solid #f59e0b;color:#92400e;">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em]" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
                                    No Changes
                                </span>
                                <p class="mt-2">{{ $message }}</p>
                            </div>
                        @enderror

                        @if ($feedbackError)
                            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ $feedbackError }}
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Department</span>
                                <select wire:model.defer="form.department_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select department</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Period Type</span>
                                <select wire:model.defer="form.period_type" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    @foreach ($periodTypes as $periodType)
                                        <option value="{{ $periodType }}">{{ ucfirst($periodType) }}</option>
                                    @endforeach
                                </select>
                                @error('form.period_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Allocated Amount (NGN)</span>
                                <input type="number" min="1" step="1" wire:model.defer="form.allocated_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="e.g. 500000">
                                @error('form.allocated_amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Period Start</span>
                                <input type="date" wire:model.defer="form.period_start" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.period_start')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Period End</span>
                                <input type="date" wire:model.defer="form.period_end" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.period_end')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closeFormModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Update Budget' : 'Create Budget' }}</span>
                                <span wire:loading wire:target="save">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
