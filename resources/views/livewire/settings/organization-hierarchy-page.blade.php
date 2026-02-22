<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="org-feedback-success-{{ $feedbackKey }}"
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
                wire:key="org-feedback-error-{{ $feedbackKey }}"
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
                Organization Control
            </span>
            <h2 class="mt-2 text-lg font-semibold text-slate-900">Hierarchy and Approval Governance</h2>
            <p class="mt-1 text-sm text-slate-600">
                Owners define departments, department heads, team reporting lines, and approval workflow chains.
                Many staff can report to one supervisor, while each user keeps a single direct manager in v1.
            </p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="fd-card p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                        Departments
                    </span>
                    <h3 class="mt-1 text-base font-semibold text-slate-900">Create Departments and Assign Heads</h3>
                </div>
            </div>

            <form wire:submit.prevent="createDepartment" class="grid gap-3 sm:grid-cols-3">
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department Name</span>
                    <input type="text" wire:model.defer="departmentForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Operations">
                    @error('departmentForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Code</span>
                    <input type="text" wire:model.defer="departmentForm.code" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="OPS">
                    @error('departmentForm.code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department Head (Optional)</span>
                    <select wire:model.defer="departmentForm.manager_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">No head assigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                        @endforeach
                    </select>
                    @error('departmentForm.manager_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        Add Department
                    </button>
                </div>
            </form>

            <div class="mt-4 space-y-2">
                @foreach ($departments as $department)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $department->name }}</p>
                                <p class="text-xs text-slate-500">{{ $department->code ?: 'No code' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <select wire:model.defer="departmentManagers.{{ $department->id }}" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">No head assigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="saveDepartmentManager({{ $department->id }})" class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="fd-card p-6">
            <div class="mb-4">
                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                    Team Setup
                </span>
                <h3 class="mt-1 text-base font-semibold text-slate-900">Create Staff and Assign Role/Reporting</h3>
            </div>

            <form wire:submit.prevent="createCompanyUser" class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Full Name</span>
                    <input type="text" wire:model.defer="newUserForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Jane Doe">
                    @error('newUserForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email</span>
                    <input type="email" wire:model.defer="newUserForm.email" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="jane@company.com">
                    @error('newUserForm.email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</span>
                    <input type="text" wire:model.defer="newUserForm.phone" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="+234...">
                    @error('newUserForm.phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Temporary Password</span>
                    <input type="password" wire:model.defer="newUserForm.password" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('newUserForm.password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Role</span>
                    <select wire:model.defer="newUserForm.role" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach ($roles as $role)
                            <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                    @error('newUserForm.role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Department</span>
                    <select wire:model.defer="newUserForm.department_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Select department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('newUserForm.department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reports To (Optional)</span>
                    <select wire:model.defer="newUserForm.reports_to_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">No direct manager</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role) }})</option>
                        @endforeach
                    </select>
                    @error('newUserForm.reports_to_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        Create Team Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Team Assignments
            </span>
            <h3 class="mt-1 text-base font-semibold text-slate-900">Role Ownership and Reporting Lines</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">User</th>
                        <th class="px-4 py-3 text-left font-semibold">Role</th>
                        <th class="px-4 py-3 text-left font-semibold">Department</th>
                        <th class="px-4 py-3 text-left font-semibold">Reports To</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr wire:key="hier-user-{{ $user->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <select wire:model.defer="userAssignments.{{ $user->id }}.role" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select wire:model.defer="userAssignments.{{ $user->id }}.department_id" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select wire:model.defer="userAssignments.{{ $user->id }}.reports_to_user_id" class="w-full rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">No direct manager</option>
                                    @foreach ($users as $managerOption)
                                        @if ($managerOption->id !== $user->id)
                                            <option value="{{ $managerOption->id }}">{{ $managerOption->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" wire:model.defer="userAssignments.{{ $user->id }}.is_active" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                    Active
                                </label>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="saveUserAssignment({{ $user->id }})" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                                    Save
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Approval Chains
            </span>
            <h3 class="mt-1 text-base font-semibold text-slate-900">Workflow Configuration for Requests</h3>
            <p class="mt-1 text-sm text-slate-600">Build policy chains using hierarchy-aware approver sources: reports_to, department_manager, role, or specific user.</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <form wire:submit.prevent="createWorkflow" class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-800">Create Workflow</p>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Workflow Name</span>
                    <input type="text" wire:model.defer="workflowForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Default Request Chain">
                    @error('workflowForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Code (Optional Override)</span>
                    <input type="text" wire:model.defer="workflowForm.code" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Leave blank to auto-generate">
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
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Create Workflow
                    </button>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="createPresetWorkflow"
                            wire:loading.attr="disabled"
                            wire:target="createPresetWorkflow"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="createPresetWorkflow">Create Preset Workflow</span>
                            <span wire:loading wire:target="createPresetWorkflow">Creating...</span>
                        </button>
                        <button
                            type="button"
                            wire:click="cleanupDuplicateWorkflows"
                            wire:loading.attr="disabled"
                            wire:target="cleanupDuplicateWorkflows"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="cleanupDuplicateWorkflows">Clean Duplicates</span>
                            <span wire:loading wire:target="cleanupDuplicateWorkflows">Cleaning...</span>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-600">
                        Preset creates a standard 2-step chain in one click: Step 1 is Direct Manager (Reports To),
                        Step 2 is Finance role approval. It also sets that workflow as default.
                    </p>
                </div>
            </form>

            <form wire:submit.prevent="addWorkflowStep" class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-800">Add Workflow Step</p>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Workflow</span>
                    <select wire:model.defer="stepForm.workflow_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Select workflow</option>
                        @foreach ($workflows as $workflow)
                            <option value="{{ $workflow->id }}">{{ $workflow->name }}</option>
                        @endforeach
                    </select>
                    @error('stepForm.workflow_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approver Source</span>
                    <select wire:model.live="stepForm.approver_source" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="reports_to">Direct Manager (Reports To)</option>
                        <option value="department_manager">Department Head</option>
                        <option value="role">Role-Based Group</option>
                        <option value="user">Specific Person</option>
                    </select>
                </label>

                @if ($stepForm['approver_source'] === 'role')
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approver Role</span>
                        <select wire:model.defer="stepForm.approver_value" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Select role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                        @error('stepForm.approver_value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                @elseif ($stepForm['approver_source'] === 'user')
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approver Person</span>
                        <select wire:model.defer="stepForm.approver_value" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Select person</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role) }})</option>
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
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                    Step numbering is automatic (next available order).
                    Amount range is optional and only needed for conditional approvals.
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Add Step
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-4 space-y-3">
            @foreach ($workflows as $workflow)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $workflow->name }}</p>
                            <p class="text-xs text-slate-500">{{ $workflow->code ?: 'No code' }} - {{ $workflow->is_default ? 'Default workflow' : 'Secondary workflow' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (! $workflow->is_default)
                                <button type="button" wire:click="setDefaultWorkflow({{ $workflow->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Set Default
                                </button>
                            @endif
                            <button
                                type="button"
                                wire:click="deleteWorkflow({{ $workflow->id }})"
                                wire:loading.attr="disabled"
                                wire:target="deleteWorkflow"
                                class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-70"
                            >
                                Delete
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
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($workflow->steps as $step)
                                    <tr>
                                        <td class="px-3 py-2 font-semibold text-slate-800">{{ $step->step_order }}</td>
                                        <td class="px-3 py-2 text-slate-700">
                                            @if ($step->step_key)
                                                {{ $step->step_key }}
                                            @elseif ($step->actor_type === 'reports_to')
                                                direct_manager_review
                                            @elseif ($step->actor_type === 'department_manager')
                                                department_head_review
                                            @elseif ($step->actor_type === 'role')
                                                role_{{ strtolower((string) $step->actor_value) }}_review
                                            @elseif ($step->actor_type === 'user')
                                                specific_user_{{ (string) $step->actor_value }}_review
                                            @else
                                                approval_step_review
                                            @endif
                                        </td>
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
                                                {{ ucfirst((string) $step->actor_value) }}
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
                                                {{ $step->min_amount ? number_format($step->min_amount) : '0' }} - {{ $step->max_amount ? number_format($step->max_amount) : 'No limit' }}
                                            @else
                                                Always
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-3 text-slate-500">No steps yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
