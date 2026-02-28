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
            @for ($i = 0; $i < 6; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-3 h-4 w-28 rounded bg-slate-200"></div>
                    <div class="mb-3 h-8 w-24 rounded bg-slate-200"></div>
                    <div class="h-3 w-36 rounded bg-slate-200"></div>
                </div>
            @endfor
        @else
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Total Tenants</p>
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
                <input type="text" wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Tenant name, slug, email">
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
            <button type="button" wire:click="openCreateModal" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">+ New Tenant</button>
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
                            <th class="px-4 py-3 text-left font-semibold">Tenant</th>
                            <th class="px-4 py-3 text-left font-semibold">Lifecycle</th>
                            <th class="px-4 py-3 text-left font-semibold">Plan / Billing</th>
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
                                <td class="px-4 py-3 text-slate-700">{{ number_format((int) $company->users_count) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('platform.tenants.show', $company) }}" class="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">Open</a>
                                        <button type="button" wire:click="openEditModal({{ $company->id }})" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">Edit</button>
                                        @if ((int) $company->users_count === 0)
                                            <button type="button" wire:click="provisionTenantLogin({{ $company->id }})" class="rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">Provision Login</button>
                                        @endif
                                        <button type="button" wire:click="openPaymentModal({{ $company->id }})" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">Record Payment</button>
                                        @if ($company->lifecycle_status === 'active')
                                            <button type="button" wire:click="suspendTenant({{ $company->id }})" class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Suspend</button>
                                        @else
                                            <button type="button" wire:click="activateTenant({{ $company->id }})" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activate</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No tenants found for selected filters.</td></tr>
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
                    <h3 class="text-lg font-semibold text-slate-900">{{ $isEditingTenant ? 'Edit Tenant' : 'Create Tenant' }}</h3>
                    <button type="button" wire:click="closeTenantModal" class="rounded-lg border border-slate-300 px-3 py-1 text-sm font-medium text-slate-600">Close</button>
                </div>
                <form wire:submit.prevent="saveTenant" class="space-y-4 px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Name</span>
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
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Plan</span>
                            <select wire:model.defer="subscriptionForm.plan_code" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="pilot">Pilot</option>
                                <option value="growth">Growth</option>
                                <option value="business">Business</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Billing Status</span>
                            <select wire:model.defer="subscriptionForm.subscription_status" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="current">Current</option>
                                <option value="grace">Grace</option>
                                <option value="overdue">Overdue</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </label>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.requests_enabled" class="rounded border-slate-300"> <span>Requests</span></label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.expenses_enabled" class="rounded border-slate-300"> <span>Expenses</span></label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.vendors_enabled" class="rounded border-slate-300"> <span>Vendors</span></label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.budgets_enabled" class="rounded border-slate-300"> <span>Budgets</span></label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.assets_enabled" class="rounded border-slate-300"> <span>Assets</span></label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.reports_enabled" class="rounded border-slate-300"> <span>Reports</span></label>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" wire:click="closeTenantModal" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveTenant" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                            <span wire:loading.remove wire:target="saveTenant">Save Tenant</span>
                            <span wire:loading wire:target="saveTenant">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPaymentModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-900/35 p-4" wire:click.self="closePaymentModal">
            <div class="mx-auto w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Record Offline Payment</h3>
                    <button type="button" wire:click="closePaymentModal" class="rounded-lg border border-slate-300 px-3 py-1 text-sm font-medium text-slate-600">Close</button>
                </div>
                <form wire:submit.prevent="saveManualPayment" class="space-y-4 px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Amount</span>
                            <input type="number" step="0.01" min="0.01" wire:model.defer="paymentForm.amount" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Currency</span>
                            <input type="text" maxlength="3" wire:model.defer="paymentForm.currency_code" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Method</span>
                            <select wire:model.defer="paymentForm.payment_method" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="offline_transfer">Offline Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Received At</span>
                            <input type="datetime-local" wire:model.defer="paymentForm.received_at" class="w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" wire:click="closePaymentModal" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveManualPayment" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                            <span wire:loading.remove wire:target="saveManualPayment">Record Payment</span>
                            <span wire:loading wire:target="saveManualPayment">Recording...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
