<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="wf-feedback-success-{{ $feedbackKey }}"
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
                wire:key="wf-feedback-error-{{ $feedbackKey }}"
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

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Workflow Governance
            </span>
            <h2 class="mt-2 text-base font-semibold text-slate-900">Approval Chains for Requests</h2>
            <p class="mt-1 text-sm text-slate-600">Easily Create and maintain policy chains across department and teams operations</p>
        </div>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Organization Communication Policy</p>
                    <p class="mt-1 text-xs text-slate-500">All channels are visible here. Step selection is only enabled for channels configured in organization settings.</p>
                </div>
                <a href="{{ route('settings.communications') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Configure Channels
                </a>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($channelPolicies as $channel => $policy)
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $policy['selectable'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $policy['label'] }}
                        @if ($policy['selectable'])
                            Ready
                        @elseif (! $policy['enabled'])
                            Disabled
                        @else
                            Not configured
                        @endif
                    </span>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="openCreateWorkflowModal"
                    wire:loading.attr="disabled"
                    wire:target="openCreateWorkflowModal"
                    class="inline-flex min-w-[170px] items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                >
                    <span>Create Workflow</span>
                    <span class="ml-2 inline-flex h-3.5 w-3.5 items-center justify-center">
                        <svg wire:loading wire:target="openCreateWorkflowModal" class="h-3.5 w-3.5 animate-spin text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                            <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="openAddStepModal"
                    wire:loading.attr="disabled"
                    wire:target="openAddStepModal"
                    class="inline-flex min-w-[170px] items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                >
                    <span>Add Workflow Step</span>
                    <span class="ml-2 inline-flex h-3.5 w-3.5 items-center justify-center">
                        <svg wire:loading wire:target="openAddStepModal" class="h-3.5 w-3.5 animate-spin text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                            <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="createPresetWorkflow"
                    wire:loading.attr="disabled"
                    wire:target="createPresetWorkflow"
                    class="inline-flex min-w-[170px] items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                >
                    <span>Create Preset Workflow</span>
                    <span class="ml-2 inline-flex h-3.5 w-3.5 items-center justify-center">
                        <svg wire:loading wire:target="createPresetWorkflow" class="h-3.5 w-3.5 animate-spin text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                            <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="cleanupDuplicateWorkflows"
                    wire:loading.attr="disabled"
                    wire:target="cleanupDuplicateWorkflows"
                    class="inline-flex min-w-[150px] items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                >
                    <span>Clean Duplicates</span>
                    <span class="ml-2 inline-flex h-3.5 w-3.5 items-center justify-center">
                        <svg wire:loading wire:target="cleanupDuplicateWorkflows" class="h-3.5 w-3.5 animate-spin text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                            <path d="M21 12a9 9 0 0 0-9-9" class="opacity-90" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                    </span>
                </button>
            </div>

            <p class="mt-1 text-xs text-slate-600">
                Preset creates a standard 2-step chain in one click: Step 1 is Direct Manager (Reports To), Step 2 is Finance role approval.
            </p>
        </div>
    </div>

    <div class="fd-card p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Configured Workflows</h3>
                <p class="text-sm text-slate-600">Review default/secondary workflows and active step chains.</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    class="rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Search workflow"
                >
                <select wire:model.live="perPage" class="rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                </select>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($workflows as $workflow)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $workflow->name }}</p>
                            <p class="text-xs text-slate-500">{{ $workflow->code ?: 'No code' }} - {{ $workflow->is_default ? 'Default workflow' : 'Secondary workflow' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (! $workflow->is_default)
                                <button
                                    type="button"
                                    wire:click="setDefaultWorkflow({{ $workflow->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="setDefaultWorkflow({{ $workflow->id }})"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                >
                                    <span wire:loading.remove wire:target="setDefaultWorkflow({{ $workflow->id }})">Set Default</span>
                                    <span wire:loading wire:target="setDefaultWorkflow({{ $workflow->id }})">Setting...</span>
                                </button>
                            @endif
                            <button
                                type="button"
                                wire:click="deleteWorkflow({{ $workflow->id }})"
                                wire:loading.attr="disabled"
                                wire:target="deleteWorkflow({{ $workflow->id }})"
                                class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-70"
                            >
                                <span wire:loading.remove wire:target="deleteWorkflow({{ $workflow->id }})">Delete</span>
                                <span wire:loading wire:target="deleteWorkflow({{ $workflow->id }})">Deleting...</span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Order</th>
                                    <th class="px-3 py-2 text-left">Step Key</th>
                                    <th class="px-3 py-2 text-left">Approver Source</th>
                                    <th class="px-3 py-2 text-left">Approver Target</th>
                                    <th class="px-3 py-2 text-left">Amount Window</th>
                                    <th class="px-3 py-2 text-left">Channels</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($workflow->steps as $step)
                                    <tr>
                                        <td class="px-3 py-2 font-semibold text-slate-800">{{ $step->step_order }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $step->step_key ?: '-' }}</td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @if ($step->actor_type === 'reports_to')
                                                Direct Manager (Reports To)
                                            @elseif ($step->actor_type === 'department_manager')
                                                Department Head
                                            @elseif ($step->actor_type === 'role')
                                                Role-Based Group
                                            @elseif ($step->actor_type === 'user')
                                                Specific Person
                                            @else
                                                {{ $step->actor_type }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @if ($step->actor_type === 'reports_to')
                                                Requester's direct manager
                                            @elseif ($step->actor_type === 'department_manager')
                                                Assigned department head
                                            @elseif ($step->actor_type === 'role')
                                                {{ ((string) $step->actor_value) === 'owner' ? 'Admin (Owner)' : ucfirst((string) $step->actor_value) }}
                                            @elseif ($step->actor_type === 'user')
                                                @php
                                                    $approverUser = $users->firstWhere('id', (int) $step->actor_value);
                                                @endphp
                                                {{ $approverUser?->name ?? ('User #'.(string) $step->actor_value) }}
                                            @else
                                                {{ $step->actor_value ?: '-' }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @if ($step->min_amount || $step->max_amount)
                                                {{ $step->min_amount ? number_format((int) $step->min_amount) : '0' }} - {{ $step->max_amount ? number_format((int) $step->max_amount) : 'No limit' }}
                                            @else
                                                Always
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @php
                                                $stepChannels = (array) ($step->notification_channels ?? []);
                                            @endphp
                                            @if ($stepChannels === [])
                                                <span class="text-xs text-slate-500">Policy default</span>
                                            @else
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($stepChannels as $stepChannel)
                                                        <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-sky-700">
                                                            {{ str_replace('_', ' ', (string) $stepChannel) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-3 text-slate-500">No steps yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
                    No workflows found for this filter.
                </div>
            @endforelse
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ $workflows->firstItem() ?? 0 }}-{{ $workflows->lastItem() ?? 0 }} of {{ $workflows->total() }}
            </p>
            {{ $workflows->links() }}
        </div>
    </div>

    @if ($showCreateWorkflowModal)
        <div wire:click="closeCreateWorkflowModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Workflow Governance
                            </span>
                            <h2 class="mt-2 text-base font-semibold text-slate-900">Create Workflow</h2>
                        </div>
                        <button type="button" wire:click="closeCreateWorkflowModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="createWorkflow" class="space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Workflow Name</span>
                            <input type="text" wire:model.defer="workflowForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Default Request Chain">
                            @error('workflowForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Code (Optional)</span>
                            <input type="text" wire:model.defer="workflowForm.code" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Auto-generated if blank">
                            @error('workflowForm.code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Description</span>
                            <textarea wire:model.defer="workflowForm.description" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('workflowForm.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" wire:model.defer="workflowForm.is_default" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                            Set as default workflow
                        </label>

                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeCreateWorkflowModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="createWorkflow"
                                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                            >
                                <span wire:loading.remove wire:target="createWorkflow">Create Workflow</span>
                                <span wire:loading wire:target="createWorkflow">Creating...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showAddStepModal)
        <div wire:click="closeAddStepModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Workflow Governance
                            </span>
                            <h2 class="mt-2 text-base font-semibold text-slate-900">Add Workflow Step</h2>
                        </div>
                        <button type="button" wire:click="closeAddStepModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="addWorkflowStep" class="space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Workflow</span>
                            <select wire:model.defer="stepForm.workflow_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Select workflow</option>
                                @foreach ($workflowsForStepForm as $workflowOption)
                                    <option value="{{ $workflowOption->id }}">{{ $workflowOption->name }}</option>
                                @endforeach
                            </select>
                            @error('stepForm.workflow_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approver Source</span>
                            <select wire:model.live="stepForm.approver_source" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Select approver source</option>
                                <option value="reports_to">Direct Manager (Reports To)</option>
                                <option value="department_manager">Department Head</option>
                                <option value="role">Role-Based Group</option>
                                <option value="user">Specific Person</option>
                            </select>
                            @error('stepForm.approver_source')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        @if ($stepForm['approver_source'] === '')
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                                Select an approver source to continue.
                            </div>
                        @elseif ($stepForm['approver_source'] === 'role')
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approver Role</span>
                                <select wire:model.defer="stepForm.approver_value" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select role</option>
                                    @foreach ($roles as $role)
                                        @if ($role !== 'staff')
                                            <option value="{{ $role }}">{{ $role === 'owner' ? 'Admin (Owner)' : ucfirst($role) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Role-based approval is for manager/finance/admin (owner)/auditor. Use Specific Person for staff approvers.</p>
                                @error('stepForm.approver_value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        @elseif ($stepForm['approver_source'] === 'user')
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approver Person</span>
                                <select wire:model.defer="stepForm.approver_value" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select person</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ ((string) $user->role) === 'owner' ? 'Admin (Owner)' : ucfirst((string) $user->role) }})</option>
                                    @endforeach
                                </select>
                                @error('stepForm.approver_value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        @else
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                                No approver target needed. This source resolves automatically from hierarchy.
                            </div>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Min Amount (Optional)</span>
                                <input type="number" min="0" wire:model.defer="stepForm.min_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('stepForm.min_amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Max Amount (Optional)</span>
                                <input type="number" min="0" wire:model.defer="stepForm.max_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('stepForm.max_amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Notification Channels</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                @foreach ($channelPolicies as $channel => $policy)
                                    <label class="inline-flex items-center gap-2 text-xs {{ $policy['selectable'] ? 'text-slate-700' : 'text-slate-400' }}">
                                        <input
                                            type="checkbox"
                                            value="{{ $channel }}"
                                            wire:model.defer="stepForm.notification_channels"
                                            @disabled(! $policy['selectable'])
                                            class="rounded border-slate-300 text-slate-700 focus:ring-slate-500"
                                        >
                                        <span>
                                            {{ $policy['label'] }}
                                            @if (! $policy['selectable'])
                                                ({{ ! $policy['enabled'] ? 'Disabled' : 'Not configured' }})
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('stepForm.notification_channels')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                            Step order is automatic in sequence per workflow. Amount range is optional for conditional approvals.
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeAddStepModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="addWorkflowStep"
                                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                            >
                                <span wire:loading.remove wire:target="addWorkflowStep">Add Step</span>
                                <span wire:loading wire:target="addWorkflowStep">Adding...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
