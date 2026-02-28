<div wire:init="loadData" class="space-y-5">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="platform-user-feedback-{{ $feedbackKey }}"
            class="pointer-events-none fixed z-[90]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg {{ $feedbackError ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                {{ $feedbackError ?: $feedbackMessage }}
            </div>
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if (! $readyToLoad)
            @for ($i = 0; $i < 4; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-3 h-4 w-28 rounded bg-slate-200"></div>
                    <div class="h-8 w-16 rounded bg-slate-200"></div>
                </div>
            @endfor
        @else
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Global Users</p>
                <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $stats['total']) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Platform Owners</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $stats['owners']) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Billing Admins</p>
                <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $stats['billing_admins']) }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Ops Admins</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) $stats['ops_admins']) }}</p>
            </div>
        @endif
    </section>

    <div class="fd-card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block lg:col-span-3">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Name or email"
                >
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rows</span>
                <select wire:model.live="perPage" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </label>
        </div>
        @if (! $canManageRoles)
            <p class="mt-3 text-xs text-amber-700">Read-only mode. Only Platform Owner can change platform roles.</p>
        @else
            <div class="mt-4 flex justify-end">
                <button
                    type="button"
                    wire:click="openCreateModal"
                    wire:loading.attr="disabled"
                    wire:target="openCreateModal"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    + New Platform User
                </button>
            </div>
        @endif
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 7; $i++)
                    <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">User</th>
                            <th class="px-4 py-3 text-left font-semibold">Current Role</th>
                            <th class="px-4 py-3 text-left font-semibold">Assign Platform Role</th>
                            <th class="px-4 py-3 text-left font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr wire:key="platform-user-row-{{ $user->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-800">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $user->platform_role ? ucwords(str_replace('_', ' ', (string) $user->platform_role)) : 'No platform role' }}
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        wire:model.defer="roleDrafts.{{ $user->id }}"
                                        @disabled(! $canManageRoles)
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option value="none">No platform role</option>
                                        @foreach ($roleOptions as $role)
                                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        wire:click="saveRole({{ $user->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="saveRole"
                                        @disabled(! $canManageRoles)
                                        class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Save
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No global users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-900/35 p-4" wire:click.self="closeCreateModal">
            <div class="mx-auto w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Create Platform User</h3>
                    <button type="button" wire:click="closeCreateModal" class="rounded-lg border border-slate-300 px-3 py-1 text-sm font-medium text-slate-600">Close</button>
                </div>

                <form wire:submit.prevent="createPlatformUser" class="space-y-4 px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Name</span>
                            <input type="text" wire:model.defer="createForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('createForm.name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email</span>
                            <input type="email" wire:model.defer="createForm.email" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('createForm.email') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Password</span>
                            <input type="password" wire:model.defer="createForm.password" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('createForm.password') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Confirm Password</span>
                            <input type="password" wire:model.defer="createForm.password_confirmation" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Platform Role</span>
                            <select wire:model.defer="createForm.platform_role" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @foreach ($roleOptions as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                            @error('createForm.platform_role') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" wire:click="closeCreateModal" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="createPlatformUser" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="createPlatformUser">Create User</span>
                            <span wire:loading wire:target="createPlatformUser">Creating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
