<div wire:init="loadData" class="space-y-5">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="vendor-feedback-success-{{ $feedbackKey }}"
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
                wire:key="vendor-feedback-error-{{ $feedbackKey }}"
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

    <div class="fd-card p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[650px]">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        placeholder="Name, email, contact"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                    <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Type</span>
                    <select wire:model.live="typeFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All types</option>
                        @foreach ($vendorTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($this->canManage)
                <button
                    type="button"
                    wire:click="openCreateModal"
                    wire:loading.attr="disabled"
                    wire:target="openCreateModal"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="openCreateModal">New Vendor</span>
                    <span wire:loading wire:target="openCreateModal">Opening...</span>
                </button>
            @else
                <p class="text-xs text-slate-500">Read-only access: owner or finance required for vendor management.</p>
            @endif
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
            <div wire:loading.flex wire:target="search,statusFilter,typeFilter,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading vendors...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold">Type</th>
                            <th class="px-4 py-3 text-left font-semibold">Contact</th>
                            <th class="px-4 py-3 text-left font-semibold">Bank Details</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($vendors as $vendor)
                            <tr wire:key="vendor-{{ $vendor->id }}" class="cursor-pointer hover:bg-slate-50" wire:click="showDetails({{ $vendor->id }})">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800">{{ $vendor->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $vendor->email ?: 'No email' }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $vendor->vendor_type ? ucfirst($vendor->vendor_type) : '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div>{{ $vendor->contact_person ?: '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ $vendor->phone ?: 'No phone' }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($vendor->bank_name || $vendor->account_name || $vendor->account_number)
                                        <div>{{ $vendor->bank_name ?: 'Bank not set' }}</div>
                                        <div class="text-xs text-slate-500">{{ $vendor->account_name ?: '-' }} {{ $vendor->account_number ? ' / '.$vendor->account_number : '' }}</div>
                                    @else
                                        <span>-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $vendor->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($this->canManage)
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click.stop="openEditModal({{ $vendor->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
                                            <button
                                                type="button"
                                                wire:click.stop="delete({{ $vendor->id }})"
                                                wire:confirm="Delete this vendor?"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No vendors found. @if ($this->canManage)Create your first vendor to start tracking suppliers and service providers.@endif</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $vendors->links() }}
            </div>
        @endif
    </div>

    @if ($showFormModal)
        <div class="fixed left-0 right-0 bottom-0 top-16 z-40 overflow-y-auto bg-slate-900/40 p-4">
            <div class="flex min-h-full items-start justify-center py-6">
            <div class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $isEditing ? 'Edit Vendor' : 'Create Vendor' }}</h2>
                        <p class="text-sm text-slate-500">Capture vendor profile and bank account details.</p>
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

                    @if ($errors->any() && ! $errors->has('form.no_changes'))
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                            Please correct the highlighted fields and submit again.
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Vendor Name</span>
                            <input type="text" wire:model.defer="form.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('form.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Type</span>
                            <select wire:model.defer="form.vendor_type" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Select type</option>
                                @foreach ($vendorTypes as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                            @error('form.vendor_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Contact Person</span>
                            <input type="text" wire:model.defer="form.contact_person" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('form.contact_person')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Phone</span>
                            <input type="text" wire:model.defer="form.phone" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('form.phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Email</span>
                            <input type="email" wire:model.defer="form.email" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('form.email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Address</span>
                        <textarea wire:model.defer="form.address" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        @error('form.address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Bank Details</p>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Bank Name</span>
                                <input type="text" wire:model.defer="form.bank_name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.bank_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Account Name</span>
                                <input type="text" wire:model.defer="form.account_name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.account_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Account Number</span>
                                <input type="text" wire:model.defer="form.account_number" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.account_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Notes</span>
                        <textarea wire:model.defer="form.notes" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        @error('form.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model.defer="form.is_active" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        Vendor is active
                    </label>
                    @error('form.is_active')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                    <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                        <button type="button" wire:click="closeFormModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                            <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Update Vendor' : 'Create Vendor' }}</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
    @endif

    @if ($showDetailPanel && $this->selectedVendor)
        @php($vendor = $this->selectedVendor)
        <div class="fixed left-0 right-0 bottom-0 top-16 z-30 bg-slate-900/20">
            <div class="absolute inset-y-0 right-0 w-full max-w-lg border-l border-slate-200 bg-white shadow-2xl">
                <div class="flex h-full flex-col">
                    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor Detail</p>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $vendor->name }}</h2>
                            <p class="text-sm text-slate-500">{{ $vendor->vendor_type ? ucfirst($vendor->vendor_type) : 'Uncategorized' }}</p>
                        </div>
                        <button type="button" wire:click="closeDetailPanel" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5 text-sm">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Contact Person</p>
                                <p class="mt-1 font-medium text-slate-800">{{ $vendor->contact_person ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Phone</p>
                                <p class="mt-1 font-medium text-slate-800">{{ $vendor->phone ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Email</p>
                                <p class="mt-1 font-medium text-slate-800">{{ $vendor->email ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Status</p>
                                <p class="mt-1 font-medium {{ $vendor->is_active ? 'text-emerald-700' : 'text-slate-600' }}">{{ $vendor->is_active ? 'Active' : 'Inactive' }}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Address</p>
                            <p class="mt-1 text-slate-800">{{ $vendor->address ?: '-' }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Bank Information</p>
                            <div class="mt-3 space-y-2">
                                <p><span class="text-slate-500">Bank:</span> <span class="font-medium text-slate-800">{{ $vendor->bank_name ?: '-' }}</span></p>
                                <p><span class="text-slate-500">Account Name:</span> <span class="font-medium text-slate-800">{{ $vendor->account_name ?: '-' }}</span></p>
                                <p><span class="text-slate-500">Account Number:</span> <span class="font-medium text-slate-800">{{ $vendor->account_number ?: '-' }}</span></p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Notes</p>
                            <p class="mt-1 text-slate-800">{{ $vendor->notes ?: '-' }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Intelligence</p>
                            <div class="mt-3 grid grid-cols-3 gap-3 text-xs">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Total Paid</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">NGN {{ number_format($this->vendorTotalPaid) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Payments</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($this->vendorPaymentsCount) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Last Paid</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $this->vendorLastPaymentDate ?? 'Never' }}</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Recent Payments</p>
                                <div class="mt-2 space-y-2">
                                    @forelse ($this->vendorRecentPayments as $payment)
                                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                            <div class="flex items-center justify-between gap-2">
                                                <div>
                                                    <p class="text-sm font-medium text-slate-800">{{ $payment['title'] }}</p>
                                                    <p class="text-xs text-slate-500">{{ $payment['expense_code'] }} • {{ $payment['department_name'] ?? 'No department' }}</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-semibold text-slate-900">NGN {{ number_format((int) $payment['amount']) }}</p>
                                                    <p class="text-xs text-slate-500">{{ $payment['expense_date'] ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500">No payments linked to this vendor yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($this->canManage)
                        <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                            <button type="button" wire:click="openEditModal({{ $vendor->id }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</button>
                            <button type="button" wire:click="delete({{ $vendor->id }})" wire:confirm="Delete this vendor?" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
