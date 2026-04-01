<div wire:init="loadData" class="space-y-5">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="tenant-feedback-{{ $feedbackKey }}"
            class="pointer-events-none fixed z-[90]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg {{ $feedbackError ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                {{ $feedbackError ?: $feedbackMessage }}
            </div>
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @if (! $readyToLoad)
            @for ($i = 0; $i < 3; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-3 h-4 w-28 rounded bg-slate-200"></div>
                    <div class="mb-3 h-8 w-24 rounded bg-slate-200"></div>
                    <div class="h-3 w-36 rounded bg-slate-200"></div>
                </div>
            @endfor
        @else
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Total Organizations</p>
                <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $stats['total']) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Active / Suspended</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $stats['active']) }} / {{ number_format((int) $stats['suspended']) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Billing Health</p>
                <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $stats['current']) }} current / {{ number_format((int) $stats['overdue']) }} overdue</p>
            </div>
        @endif
    </section>

    <div class="fd-card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Organization name, slug, email">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Lifecycle</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                    <option value="archived">Archived</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Plan</span>
                <select wire:model.live="planFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All plans</option>
                    <option value="pilot">Pilot</option>
                    <option value="growth">Growth</option>
                    <option value="business">Business</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Billing</span>
                <select wire:model.live="billingFilter" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="all">All billing states</option>
                    <option value="current">Current</option>
                    <option value="grace">Grace</option>
                    <option value="overdue">Overdue</option>
                    <option value="suspended">Suspended</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rows</span>
                <select wire:model.live="perPage" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </label>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" wire:click="openCreateModal" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">+ New Organization</button>
        </div>
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
                            <th class="px-4 py-3 text-left font-semibold">Organization</th>
                            <th class="px-4 py-3 text-left font-semibold">Lifecycle</th>
                            <th class="px-4 py-3 text-left font-semibold">Plan / Billing</th>
                            <th class="px-4 py-3 text-left font-semibold">Created At</th>
                            <th class="px-4 py-3 text-left font-semibold">Trial Period</th>
                            <th class="px-4 py-3 text-left font-semibold">Users</th>
                            <th class="px-4 py-3 text-left font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($companies as $company)
                            @php
                                $lifecycleClass = match ((string) $company->lifecycle_status) {
                                    'active' => 'bg-emerald-100 text-emerald-700',
                                    'suspended' => 'bg-amber-100 text-amber-700',
                                    'inactive', 'archived' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr wire:key="tenant-row-{{ $company->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-800">{{ $company->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $company->slug }} - {{ $company->email ?: 'no email' }}</p>
                                </td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $lifecycleClass }}">{{ ucfirst((string) $company->lifecycle_status) }}</span></td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p class="font-medium">{{ ucfirst((string) ($company->subscription?->plan_code ?: 'pilot')) }}</p>
                                    <p class="text-xs text-slate-500">{{ ucfirst((string) ($company->subscription?->subscription_status ?: 'current')) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @php
                                        $timezone = trim((string) ($company->timezone ?: config('app.timezone', 'Africa/Lagos')));
                                        $createdAt = $company->created_at
                                            ? $company->created_at->timezone($timezone)->format('M d, Y H:i')
                                            : '-';
                                        $trialStart = $company->subscription?->trial_started_at
                                            ? $company->subscription->trial_started_at->timezone($timezone)->format('M d, Y H:i')
                                            : null;
                                        $trialEnd = $company->subscription?->trial_ends_at
                                            ? $company->subscription->trial_ends_at->timezone($timezone)->format('M d, Y H:i')
                                            : null;
                                    @endphp
                                    <p class="font-medium">{{ $createdAt }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p class="font-medium">{{ $trialStart ?: '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $trialEnd ? 'Ends: '.$trialEnd : 'No trial end set' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ number_format((int) $company->users_count) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('platform.tenants.show', $company) }}" class="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">Details</a>
                                        <button type="button" wire:click="openEditModal({{ $company->id }})" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">Update</button>
                                        <a href="{{ route('platform.tenants.plan-entitlements', $company) }}" class="rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">Plan & Modules</a>
                                        <a href="{{ route('platform.tenants.billing', $company) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Billing</a>
                                        <a href="{{ route('platform.tenants.execution-mode', $company) }}" class="rounded-lg border border-violet-300 bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">Execution Mode</a>
                                        <a href="{{ route('platform.tenants.execution-policy', $company) }}" class="rounded-lg border border-fuchsia-300 bg-fuchsia-50 px-2.5 py-1 text-xs font-semibold text-fuchsia-700">Execution Policy</a>
                                        @if ((int) $company->users_count === 0)
                                            <button type="button" wire:click="provisionTenantLogin({{ $company->id }})" class="rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">Provision Login</button>
                                        @endif
                                        @if ($company->lifecycle_status === 'active')
                                            <button type="button" wire:click="suspendTenant({{ $company->id }})" class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Suspend</button>
                                        @else
                                            <button type="button" wire:click="activateTenant({{ $company->id }})" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activate</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No organizations found for selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $companies->links() }}</div>
        @endif
    </div>

    @if ($showTenantModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-900/35 p-4" wire:click.self="closeTenantModal">
            <div class="mx-auto w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">{{ $isEditingTenant ? 'Update Organization' : 'Create Organization' }}</h3>
                    <button type="button" wire:click="closeTenantModal" class="rounded-lg border border-slate-300 px-3 py-1 text-sm font-medium text-slate-600">Close</button>
                </div>
                <form wire:submit.prevent="saveTenant" class="space-y-4 px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization Name</span>
                            <input type="text" wire:model.defer="tenantForm.name" class="w-full rounded-xl border-slate-300 text-sm">
                            @error('tenantForm.name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Slug</span>
                            <input type="text" wire:model.defer="tenantForm.slug" class="w-full rounded-xl border-slate-300 text-sm">
                            @error('tenantForm.slug') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email</span>
                            <input type="email" wire:model.defer="tenantForm.email" class="w-full rounded-xl border-slate-300 text-sm">
                            @error('tenantForm.email') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</span>
                            <input type="text" wire:model.defer="tenantForm.phone" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Industry</span>
                            <input type="text" wire:model.defer="tenantForm.industry" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Currency</span>
                            <input type="text" maxlength="3" wire:model.defer="tenantForm.currency_code" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Timezone</span>
                            <input type="text" wire:model.defer="tenantForm.timezone" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Lifecycle</span>
                            <select wire:model.defer="tenantForm.lifecycle_status" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </select>
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Address</span>
                        <textarea rows="2" wire:model.defer="tenantForm.address" class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </label>

                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" wire:click="closeTenantModal" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveTenant" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                            <span wire:loading.remove wire:target="saveTenant">Save Organization</span>
                            <span wire:loading wire:target="saveTenant">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>




