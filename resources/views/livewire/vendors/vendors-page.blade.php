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
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-3">
            <div class="grid gap-3 sm:grid-cols-4 lg:flex-1 lg:grid-cols-[minmax(180px,1.35fr)_minmax(110px,0.75fr)_minmax(130px,0.9fr)_minmax(88px,0.55fr)]">
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

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rows</span>
                    <select wire:model.live="perPage" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('vendors.reports') }}"
                    class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-slate-200 px-3.5 text-sm font-bold text-slate-900 transition hover:bg-slate-300"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3 4.5A1.5 1.5 0 014.5 3h11A1.5 1.5 0 0117 4.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 15.5v-11zM6 7a1 1 0 100 2h8a1 1 0 100-2H6zm0 4a1 1 0 100 2h5a1 1 0 100-2H6z"/>
                    </svg>
                    <span>Vendor Reports</span>
                </a>

                @if ($this->canCreateVendor)
                    <button
                        type="button"
                        wire:click="openCreateModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateModal"
                        class="inline-flex h-10 shrink-0 items-center whitespace-nowrap rounded-xl bg-slate-900 px-3.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="openCreateModal" class="inline-flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Vendor</span>
                        </span>
                        <span wire:loading wire:target="openCreateModal">Opening...</span>
                    </button>
                @endif

                <a
                    href="{{ route('vendors.index') }}"
                    class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <span aria-hidden="true">&larr;</span>
                    <span>Back to Vendor Management</span>
                </a>
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
            <div wire:loading.flex wire:target="search,statusFilter,typeFilter,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
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
                            <tr wire:key="vendor-{{ $vendor->id }}" class="hover:bg-slate-50">
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
                                    @if ($vendor->bank_name || $vendor->bank_code || $vendor->account_name || $vendor->account_number)
                                        <div>{{ $vendor->bank_name ?: 'Bank not set' }}</div>
                                        <div class="text-xs text-slate-500">{{ $vendor->account_name ?: '-' }} {{ $vendor->account_number ? ' / '.$vendor->account_number : '' }}{{ $vendor->bank_code ? ' ('.$vendor->bank_code.')' : '' }}</div>
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
                                    <div class="flex justify-end gap-2">
                                        <a
                                            href="{{ route('vendors.show', ['vendor' => $vendor->id]) }}"
                                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Details
                                        </a>
                                        @can('update', $vendor)
                                            <button
                                                type="button"
                                                wire:click.stop="openEditModal({{ $vendor->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="openEditModal({{ $vendor->id }})"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="openEditModal({{ $vendor->id }})">Edit</span>
                                                <span wire:loading wire:target="openEditModal({{ $vendor->id }})">Opening...</span>
                                            </button>
                                        @endcan
                                        @can('delete', $vendor)
                                            <button
                                                type="button"
                                                wire:click.stop="delete({{ $vendor->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="delete({{ $vendor->id }})"
                                                wire:confirm="Delete this vendor?"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="delete({{ $vendor->id }})">Delete</span>
                                                <span wire:loading wire:target="delete({{ $vendor->id }})">Deleting...</span>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No vendors found. @if ($this->canCreateVendor)Create your first vendor to start tracking suppliers and service providers.@endif</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        Showing {{ $vendors->firstItem() ?? 0 }}-{{ $vendors->lastItem() ?? 0 }} of {{ $vendors->total() }}
                    </p>
                    {{ $vendors->links() }}
                </div>
            </div>
                        
            </div>

        @endif
    @if ($showFormModal)
        <div wire:click="closeFormModal" class="fixed left-0 right-0 bottom-0 top-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
            <div wire:click.stop class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
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
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Bank Name</span>
                                <input type="text" wire:model.defer="form.bank_name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.bank_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Bank Code</span>
                                <input type="text" wire:model.defer="form.bank_code" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="e.g. 058">
                                @error('form.bank_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
        @php
            $vendor = $this->selectedVendor;
        @endphp
        <div wire:click="closeDetailPanel" class="fixed bottom-0 left-0 right-0 top-16 z-30 bg-slate-900/20 md:left-64">
            <div wire:click.stop class="absolute inset-y-0 right-0 w-full max-w-4xl border-l border-slate-200 bg-white shadow-2xl">
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
                                <p><span class="text-slate-500">Bank Code:</span> <span class="font-medium text-slate-800">{{ $vendor->bank_code ?: '-' }}</span></p>
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
                                                    <p class="text-xs text-slate-500">{{ $payment['expense_code'] }} &middot; {{ $payment['department_name'] ?? 'No department' }}</p>
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

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Invoice Ledger</p>
                                    <p class="mt-1 text-xs text-slate-500">Track invoice obligations, settlement progress, and outstanding balance.</p>
                                </div>
                                @if ($this->canManageVendorFinance)
                                    <button
                                        type="button"
                                        wire:click="openCreateInvoiceModal"
                                        wire:loading.attr="disabled"
                                        wire:target="openCreateInvoiceModal"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Create Invoice</span>
                                    </button>
                                @endif
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-4 text-xs">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Total Invoiced</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">NGN {{ number_format($this->vendorTotalInvoiced) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Paid on Invoices</p>
                                    <p class="mt-1 text-sm font-semibold text-emerald-700">NGN {{ number_format($this->vendorTotalInvoicePaid) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Outstanding</p>
                                    <p class="mt-1 text-sm font-semibold text-amber-700">NGN {{ number_format($this->vendorOutstandingBalance) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="uppercase tracking-[0.1em] text-slate-500">Invoices</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($this->vendorInvoicesCount) }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em]">
                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-600">Unpaid: {{ $this->vendorUnpaidInvoicesCount }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700">Part-Paid Invoices: {{ $this->vendorPartPaidInvoicesCount }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-blue-700">Part Payments: {{ $this->vendorPartPaymentsCount }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-rose-700">Overdue: {{ $this->vendorOverdueInvoicesCount }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Paid: {{ $this->vendorPaidInvoicesCount }}</span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <label class="block sm:col-span-2">
                                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Invoices</span>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="invoiceSearch"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                                        placeholder="Invoice number, description, status"
                                    >
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Status</span>
                                    <select wire:model.live="invoiceStatusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                        <option value="all">All statuses</option>
                                        @foreach ($invoiceStatuses as $status)
                                            <option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <div class="mt-4 space-y-2">
                                @forelse ($this->filteredVendorInvoices as $invoice)
                                    @php
                                        $statusClass = match ($invoice['display_status']) {
                                            'paid' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                            'part_paid' => 'bg-blue-100 text-blue-700 border-blue-200',
                                            'overdue' => 'bg-rose-100 text-rose-700 border-rose-200',
                                            'void' => 'bg-rose-100 text-rose-700 border-rose-200',
                                            default => 'bg-amber-100 text-amber-700 border-amber-200',
                                        };
                                    @endphp
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">{{ $invoice['invoice_number'] }}</p>
                                                <p class="text-xs text-slate-500">{{ $invoice['invoice_date'] ?? '-' }} @if($invoice['due_date']) &middot; Due {{ $invoice['due_date'] }} @endif</p>
                                                @if ($invoice['description'])
                                                    <p class="mt-1 text-xs text-slate-600">{{ $invoice['description'] }}</p>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $statusClass }}">
                                                    {{ str_replace('_', ' ', $invoice['display_status']) }}
                                                </span>
                                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $invoice['currency'] }} {{ number_format((int) $invoice['total_amount']) }}</p>
                                                <p class="text-xs text-slate-500">Outstanding: {{ $invoice['currency'] }} {{ number_format((int) $invoice['outstanding_amount']) }}</p>
                                                @if (! empty($invoice['due_countdown']))
                                                    <p class="text-xs {{ $invoice['is_overdue'] ? 'text-rose-600' : 'text-slate-500' }}">{{ $invoice['due_countdown'] }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-slate-600">
                                                Invoice files: {{ $invoice['attachment_count'] ?? 0 }}
                                            </span>
                                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-slate-600">
                                                Payment proofs: {{ $invoice['payment_attachment_count'] ?? 0 }}
                                            </span>
                                        </div>
                                        @if (! empty($invoice['attachments']))
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($invoice['attachments'] as $attachment)
                                                    <a
                                                        href="{{ $this->vendorInvoiceAttachmentDownloadUrlById((int) $attachment['id']) }}"
                                                        target="_blank"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-600 hover:bg-slate-50"
                                                    >
                                                        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path d="M7.5 6.5a2.5 2.5 0 015 0V10a4 4 0 11-8 0V6.5a1 1 0 112 0V10a2 2 0 104 0V6.5a.5.5 0 00-1 0V10a1 1 0 11-2 0V6.5z" />
                                                        </svg>
                                                        <span>{{ $attachment['original_name'] }}</span>
                                                        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v7.59l2.3-2.3a1 1 0 111.4 1.42l-4 3.9a1 1 0 01-1.4 0l-4-3.9a1 1 0 111.4-1.42l2.3 2.3V4a1 1 0 011-1zm-6 13a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($this->canManageVendorFinance || $this->canRecordVendorPayments)
                                            <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
                                                @if ($this->canManageVendorFinance)
                                                    <button type="button" wire:click="openEditInvoiceModal({{ $invoice['id'] }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
                                                @endif
                                                @if ($this->canRecordVendorPayments && $invoice['can_receive_payment'])
                                                    <button type="button" wire:click="openPaymentModal({{ $invoice['id'] }})" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Record Payment</button>
                                                @endif
                                                @if ($this->canManageVendorFinance && $invoice['status'] !== 'void')
                                                    <button type="button" wire:click="openVoidInvoiceModal({{ $invoice['id'] }})" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Void</button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-6 text-center text-xs text-slate-500">
                                        No invoices match this filter yet.
                                    </p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Statement Timeline</p>
                            <div class="mt-3 space-y-2">
                                @forelse ($this->vendorStatementTimeline as $event)
                                    @php
                                        $isPayment = $event['event_type'] === 'payment';
                                        $timelineClass = $isPayment
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-white text-slate-600';
                                    @endphp
                                    <div class="rounded-lg border px-3 py-2 {{ $timelineClass }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-semibold">{{ $event['title'] }}</p>
                                                <p class="text-xs uppercase tracking-[0.08em]">{{ str_replace('_', ' ', $event['event_subtype']) }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-semibold">NGN {{ number_format((int) $event['amount']) }}</p>
                                                <p class="text-xs">{{ $event['happened_at_label'] }}</p>
                                            </div>
                                        </div>
                                        @if ($isPayment && ! empty($event['meta']['attachments']))
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($event['meta']['attachments'] as $attachment)
                                                    <a
                                                        href="{{ $this->vendorPaymentAttachmentDownloadUrlById((int) $attachment['id']) }}"
                                                        target="_blank"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1 text-[11px] text-emerald-700 hover:bg-emerald-50"
                                                    >
                                                        <svg class="h-3.5 w-3.5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path d="M7.5 6.5a2.5 2.5 0 015 0V10a4 4 0 11-8 0V6.5a1 1 0 112 0V10a2 2 0 104 0V6.5a.5.5 0 00-1 0V10a1 1 0 11-2 0V6.5z" />
                                                        </svg>
                                                        <span>{{ $attachment['original_name'] }}</span>
                                                        <svg class="h-3.5 w-3.5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v7.59l2.3-2.3a1 1 0 111.4 1.42l-4 3.9a1 1 0 01-1.4 0l-4-3.9a1 1 0 111.4-1.42l2.3 2.3V4a1 1 0 011-1zm-6 13a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-6 text-center text-xs text-slate-500">
                                        No invoice or payment timeline yet.
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if ($this->canManageVendorProfile)
                        <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                            @can('update', $vendor)
                                <button
                                    type="button"
                                    wire:click="openEditModal({{ $vendor->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openEditModal"
                                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                >
                                    <span wire:loading.remove wire:target="openEditModal">Edit</span>
                                    <span wire:loading wire:target="openEditModal">Opening...</span>
                                </button>
                            @endcan
                            @can('delete', $vendor)
                                <button
                                    type="button"
                                    wire:click="delete({{ $vendor->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="delete"
                                    wire:confirm="Delete this vendor?"
                                    class="rounded-xl border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-70"
                                >
                                    <span wire:loading.remove wire:target="delete">Delete</span>
                                    <span wire:loading wire:target="delete">Deleting...</span>
                                </button>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showInvoiceModal)
        <div wire:click="closeInvoiceModal" class="fixed left-0 right-0 bottom-0 top-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                                Vendor Invoice
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $isEditingInvoice ? 'Edit Invoice' : 'Create Invoice' }}</h2>
                        </div>
                        <button type="button" wire:click="closeInvoiceModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveInvoice" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Invoice Number</span>
                                <input type="text" wire:model.defer="invoiceForm.invoice_number" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('invoiceForm.invoice_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Total Amount</span>
                                <input type="number" min="1" step="1" wire:model.defer="invoiceForm.total_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('invoiceForm.total_amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Invoice Date</span>
                                <input type="date" wire:model.defer="invoiceForm.invoice_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('invoiceForm.invoice_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Due Date (Optional)</span>
                                <input type="date" wire:model.defer="invoiceForm.due_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('invoiceForm.due_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Description (Optional)</span>
                            <textarea wire:model.defer="invoiceForm.description" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('invoiceForm.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Notes (Optional)</span>
                            <textarea wire:model.defer="invoiceForm.notes" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('invoiceForm.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closeInvoiceModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveInvoice" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveInvoice">{{ $isEditingInvoice ? 'Update Invoice' : 'Save Invoice' }}</span>
                                <span wire:loading wire:target="saveInvoice">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showPaymentModal)
        @php
            $payingInvoice = collect($this->vendorInvoices)->firstWhere('id', $payingInvoiceId);
        @endphp
        <div wire:click="closePaymentModal" class="fixed left-0 right-0 bottom-0 top-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-2xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-emerald-700">
                                Invoice Payment
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Record Payment</h2>
                            @if ($payingInvoice)
                                <p class="text-xs text-slate-500">{{ $payingInvoice['invoice_number'] }} &middot; Outstanding {{ $payingInvoice['currency'] }} {{ number_format((int) $payingInvoice['outstanding_amount']) }}</p>
                            @endif
                        </div>
                        <button type="button" wire:click="closePaymentModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="recordInvoicePayment" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Amount</span>
                                <input type="number" min="1" step="1" wire:model.defer="paymentForm.amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('paymentForm.amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Payment Date</span>
                                <input type="date" wire:model.defer="paymentForm.payment_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('paymentForm.payment_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Payment Method (Optional)</span>
                                <select wire:model.defer="paymentForm.payment_method" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select method</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method }}">{{ strtoupper($method) }}</option>
                                    @endforeach
                                </select>
                                @error('paymentForm.payment_method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Payment Reference (Optional)</span>
                                <input type="text" wire:model.defer="paymentForm.payment_reference" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('paymentForm.payment_reference')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Notes (Optional)</span>
                            <textarea wire:model.defer="paymentForm.notes" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('paymentForm.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        @error('invoice')
                            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</div>
                        @enderror

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closePaymentModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="recordInvoicePayment" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="recordInvoicePayment">Save Payment</span>
                                <span wire:loading wire:target="recordInvoicePayment">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showVoidInvoiceModal)
        <div wire:click="closeVoidInvoiceModal" class="fixed left-0 right-0 bottom-0 top-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-rose-700">
                                Void Invoice
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Void Invoice</h2>
                            <p class="text-xs text-slate-500">Voiding is permanent and requires a reason.</p>
                        </div>
                        <button type="button" wire:click="closeVoidInvoiceModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="submitVoidInvoice" class="space-y-4">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Reason</span>
                            <textarea wire:model.defer="voidInvoiceReason" rows="4" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="State why this invoice is being voided"></textarea>
                            @error('voidInvoiceReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closeVoidInvoiceModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="submitVoidInvoice" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="submitVoidInvoice">Void Invoice</span>
                                <span wire:loading wire:target="submitVoidInvoice">Voiding...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

