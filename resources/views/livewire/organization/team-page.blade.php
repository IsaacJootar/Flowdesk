<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="team-feedback-success-{{ $feedbackKey }}"
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
                wire:key="team-feedback-error-{{ $feedbackKey }}"
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
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Team Assignments</h3>
                <p class="text-sm text-slate-600">Update role ownership, department mapping, and reports-to lines.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="openCreateModal"
                    wire:loading.attr="disabled"
                    wire:target="openCreateModal"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <svg class="mr-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    <span wire:loading.remove wire:target="openCreateModal">Create Staff</span>
                    <span wire:loading wire:target="openCreateModal">Opening...</span>
                </button>
                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    class="rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Search team members"
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
                            <th class="px-4 py-3 text-left font-semibold">User</th>
                            <th class="px-4 py-3 text-left font-semibold">Role</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Reports To</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr wire:key="team-row-{{ $user->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($user->avatar_path)
                                            <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white">
                                                <img
                                                    src="{{ route('users.avatar', ['user' => $user->id, 'v' => optional($user->updated_at)->timestamp]) }}"
                                                    alt="{{ $user->name }}"
                                                    class="h-full w-full object-cover"
                                                    style="object-position: center 30%;"
                                                    loading="lazy"
                                                >
                                            </div>
                                        @else
                                            @php
                                                $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
                                                $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : '';
                                                $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';
                                                $initials = $first.$second;

                                                $gender = strtolower((string) ($user->gender ?? 'other'));
                                                $avatarBackground = '#ede9fe';
                                                $avatarBorder = '#c4b5fd';
                                                $avatarText = '#4c1d95';

                                                if ($gender === 'male') {
                                                    $avatarBackground = '#dbeafe';
                                                    $avatarBorder = '#93c5fd';
                                                    $avatarText = '#1e3a8a';
                                                } elseif ($gender === 'female') {
                                                    $avatarBackground = '#fce7f3';
                                                    $avatarBorder = '#f9a8d4';
                                                    $avatarText = '#831843';
                                                }
                                            @endphp
                                            <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border text-[13px] font-semibold" style="background-color: {{ $avatarBackground }}; border-color: {{ $avatarBorder }}; color: {{ $avatarText }};">
                                                <span>
                                                    {{ $initials !== '' ? $initials : '?' }}
                                                </span>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-medium text-slate-800">{{ $user->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
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
                                        @foreach ($managerOptions as $managerOption)
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
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="openProfileModal({{ $user->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openProfileModal"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="openProfileModal">Profile</span>
                                            <span wire:loading wire:target="openProfileModal">Opening...</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="saveUserAssignment({{ $user->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="saveUserAssignment"
                                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="saveUserAssignment">Save</span>
                                            <span wire:loading wire:target="saveUserAssignment">Saving...</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                                    No team members found for this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}
            </p>
            {{ $users->links() }}
        </div>
    </div>

    @if ($showCreateModal)
        <div class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Team Setup
                            </span>
                            <h2 class="mt-2 text-base font-semibold text-slate-900">Create Team Member</h2>
                        </div>
                        <button type="button" wire:click="closeCreateModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="createCompanyUser" class="space-y-3">
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

                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</span>
                                <input type="text" wire:model.defer="newUserForm.phone" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="+234...">
                                @error('newUserForm.phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Gender</span>
                                <select wire:model.defer="newUserForm.gender" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('newUserForm.gender')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Temporary Password</span>
                            <input type="password" wire:model.defer="newUserForm.password" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('newUserForm.password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reports To</span>
                            <select wire:model.defer="newUserForm.reports_to_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">No direct manager</option>
                                @foreach ($managerOptions as $managerOption)
                                    <option value="{{ $managerOption->id }}">{{ $managerOption->name }} ({{ ucfirst((string) $managerOption->role) }})</option>
                                @endforeach
                            </select>
                            @error('newUserForm.reports_to_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Staff Photo (Optional)</span>
                            <input type="file" wire:model="avatarUpload" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                            <p class="mt-1 text-xs text-slate-500">Square photo preferred. Max 2MB.</p>
                            @error('avatarUpload')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <div wire:loading wire:target="avatarUpload" class="mt-1 text-xs text-slate-600">Uploading photo...</div>
                        </label>

                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeCreateModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="createCompanyUser,avatarUpload"
                                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                            >
                                <span wire:loading.remove wire:target="createCompanyUser">Create Team Member</span>
                                <span wire:loading wire:target="createCompanyUser">Creating...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showProfileModal && $this->profileUser)
        <div class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Staff Profile
                            </span>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Edit {{ $this->profileUser->name }}</h3>
                        </div>
                        <button type="button" wire:click="closeProfileModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="saveUserProfile" class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Full Name</span>
                                <input type="text" wire:model.defer="profileForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('profileForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email</span>
                                <input type="email" wire:model.defer="profileForm.email" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('profileForm.email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</span>
                                <input type="text" wire:model.defer="profileForm.phone" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('profileForm.phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Gender</span>
                                <select wire:model.defer="profileForm.gender" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('profileForm.gender')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Replace Photo (Optional)</span>
                            <input type="file" wire:model="profileAvatarUpload" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                            @error('profileAvatarUpload')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <div wire:loading wire:target="profileAvatarUpload" class="mt-1 text-xs text-slate-600">Uploading photo...</div>
                        </label>

                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeProfileModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveUserProfile,profileAvatarUpload" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveUserProfile">Save Profile</span>
                                <span wire:loading wire:target="saveUserProfile">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
