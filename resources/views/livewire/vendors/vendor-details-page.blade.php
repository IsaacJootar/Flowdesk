<div class="space-y-5">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="vendor-detail-feedback-success-{{ $feedbackKey }}"
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
                wire:key="vendor-detail-feedback-error-{{ $feedbackKey }}"
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

    @if (! $vendor)
        <div class="fd-card p-6">
            <p class="text-sm text-slate-600">Vendor not found or no longer available.</p>
            <a href="{{ route('vendors.index') }}" class="mt-3 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Back to Vendor Directory
            </a>
        </div>
    @else
        <div class="fd-card p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <a href="{{ route('vendors.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500 hover:text-slate-700">
                        <span aria-hidden="true">&larr;</span>
                        <span>Back to Vendor Directory</span>
                    </a>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $vendor->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $vendor->vendor_type ? ucfirst($vendor->vendor_type) : 'Uncategorized' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($this->canManageVendorProfile)
                        @can('update', $vendor)
                            <button
                                type="button"
                                wire:click="openEditModal({{ $vendor->id }})"
                                wire:loading.attr="disabled"
                                wire:target="openEditModal"
                                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                            >
                                <span wire:loading.remove wire:target="openEditModal" class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                    </svg>
                                    <span>Edit Vendor</span>
                                </span>
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
                                <span wire:loading.remove wire:target="delete">Delete Vendor</span>
                                <span wire:loading wire:target="delete">Deleting...</span>
                            </button>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        <div class="fd-card p-5 space-y-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <p><span class="text-slate-500">Bank:</span> <span class="font-medium text-slate-800">{{ $vendor->bank_name ?: '-' }}</span></p>
                    <p><span class="text-slate-500">Bank Code:</span> <span class="font-medium text-slate-800">{{ $vendor->bank_code ?: '-' }}</span></p>
                    <p><span class="text-slate-500">Account Name:</span> <span class="font-medium text-slate-800">{{ $vendor->account_name ?: '-' }}</span></p>
                    <p><span class="text-slate-500">Account Number:</span> <span class="font-medium text-slate-800">{{ $vendor->account_number ?: '-' }}</span></p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Intelligence</p>
                <div class="mt-3 grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
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
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Statement Export Center</p>
                    <span class="text-[11px] text-slate-500">CSV + Print</span>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">From</span>
                        <input
                            type="date"
                            wire:model.live="statementDateFrom"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">To</span>
                        <input
                            type="date"
                            wire:model.live="statementDateTo"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Invoice Status</span>
                        <select
                            wire:model.live="statementInvoiceStatus"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                        >
                            <option value="all">All</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="part_paid">Part Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="paid">Paid</option>
                            <option value="void">Void</option>
                        </select>
                    </label>
                </div>

                @if ($this->canExportVendorStatements)
                    <div class="flex flex-wrap gap-2">
                        <a
                            href="{{ $this->vendorStatementCsvUrl((int) $vendor->id) }}"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 4.5A1.5 1.5 0 014.5 3h7.879a1.5 1.5 0 011.06.44l2.12 2.121a1.5 1.5 0 01.44 1.06V15.5A1.5 1.5 0 0114.5 17h-10A1.5 1.5 0 013 15.5v-11zM6 8.5a.75.75 0 000 1.5h8a.75.75 0 000-1.5H6zm0 3a.75.75 0 000 1.5h8a.75.75 0 000-1.5H6z" clip-rule="evenodd"/>
                            </svg>
                            <span>Export Statement CSV</span>
                        </a>
                        <a
                            href="{{ $this->vendorStatementPrintUrl((int) $vendor->id) }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M5 4a2 2 0 00-2 2v2h14V6a2 2 0 00-2-2H5z" />
                                <path fill-rule="evenodd" d="M3 10h14v4a2 2 0 01-2 2h-1v-3H6v3H5a2 2 0 01-2-2v-4zm5 4v3h4v-3H8z" clip-rule="evenodd" />
                            </svg>
                            <span>Print Statement</span>
                        </a>
                    </div>
                @endif
            </div>

            @if ($this->canManageVendorCommunications)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reminder Automation</p>
                        <span class="text-[11px] text-slate-500">Finance inbox/email</span>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Days Ahead</span>
                            <input
                                type="number"
                                min="0"
                                max="30"
                                wire:model.live="reminderDaysAhead"
                                class="w-28 rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                            >
                        </label>
                        <button
                            type="button"
                            wire:click="sendDueInvoiceReminders"
                            wire:loading.attr="disabled"
                            wire:target="sendDueInvoiceReminders"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M2.94 6.94a1.5 1.5 0 011.06-.44h12a1.5 1.5 0 011.5 1.5v7A1.5 1.5 0 0116 16.5H4A1.5 1.5 0 012.5 15V8a1.5 1.5 0 01.44-1.06zM4.5 9v6h11V9l-5.2 3.25a1.5 1.5 0 01-1.6 0L4.5 9z"/>
                            </svg>
                            <span wire:loading.remove wire:target="sendDueInvoiceReminders">Run Due Reminders</span>
                            <span wire:loading wire:target="sendDueInvoiceReminders">Processing...</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-500">Sends due/overdue reminders to finance team.</p>
                </div>
            @endif
        </div>

        <div class="fd-card p-5 space-y-4">
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

            <div class="grid gap-3 text-xs sm:grid-cols-4">
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

            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em]">
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-600">Unpaid: {{ $this->vendorUnpaidInvoicesCount }}</span>
                <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700">Part-Paid Invoices: {{ $this->vendorPartPaidInvoicesCount }}</span>
                <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-blue-700">Part Payments: {{ $this->vendorPartPaymentsCount }}</span>
                <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-rose-700">Overdue: {{ $this->vendorOverdueInvoicesCount }}</span>
                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700">Paid: {{ $this->vendorPaidInvoicesCount }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
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

            <div class="space-y-2">
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
                                    <p class="mt-1 text-xs text-slate-500">{{ $invoice['description'] }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-slate-900">{{ $invoice['currency'] }} {{ number_format((int) $invoice['total_amount']) }}</p>
                                <p class="text-xs text-emerald-700">Paid: {{ $invoice['currency'] }} {{ number_format((int) $invoice['paid_amount']) }}</p>
                                <p class="text-xs text-amber-700">Outstanding: {{ $invoice['currency'] }} {{ number_format((int) $invoice['outstanding_amount']) }}</p>
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
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $statusClass }}">
                                {{ ucwords(str_replace('_', ' ', $invoice['display_status'])) }}
                            </span>
                            @if (! empty($invoice['due_countdown']))
                                <span class="text-xs {{ $invoice['is_overdue'] ? 'text-rose-600' : 'text-slate-500' }}">{{ $invoice['due_countdown'] }}</span>
                            @endif
                            @if ($this->canManageVendorFinance || $this->canRecordVendorPayments)
                                <div class="flex flex-wrap gap-2">
                                    @if ($this->canRecordVendorPayments && $invoice['can_receive_payment'])
                                        <button type="button" wire:click="openPaymentModal({{ $invoice['id'] }})" class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Record Payment</button>
                                    @endif
                                    @if ($this->canManageVendorFinance)
                                        <button type="button" wire:click="openEditInvoiceModal({{ $invoice['id'] }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            <span class="inline-flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M12 20h9"></path>
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                                </svg>
                                                <span>Edit Invoice</span>
                                            </span>
                                        </button>
                                    @endif
                                    @if ($this->canManageVendorFinance && $invoice['status'] !== 'void')
                                        <button type="button" wire:click="openVoidInvoiceModal({{ $invoice['id'] }})" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Void</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-6 text-center text-xs text-slate-500">
                        No invoices found for this vendor.
                    </p>
                @endforelse
            </div>
        </div>

        <div class="fd-card p-5">
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

        <div class="fd-card p-5">
            @if ($this->canManageVendorCommunications)
                <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Delivery Retry Center</p>
                        <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                            <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-red-700">
                                Failed: {{ (int) ($this->vendorCommunicationSummary['failed'] ?? 0) }}
                            </span>
                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-amber-700">
                                Stuck queued: {{ (int) ($this->vendorCommunicationSummary['queued_stuck'] ?? 0) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                            <span>Older than</span>
                            <input
                                type="number"
                                min="0"
                                wire:model.live.debounce.400ms="vendorCommQueuedOlderThanMinutes"
                                class="w-16 rounded-lg border-slate-300 px-2 py-1 text-xs focus:border-slate-500 focus:ring-slate-500"
                            >
                            <span>min</span>
                        </label>
                        <button
                            type="button"
                            wire:click="retryFailedVendorCommunications"
                            wire:loading.attr="disabled"
                            wire:target="retryFailedVendorCommunications"
                            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="retryFailedVendorCommunications">Retry Failed</span>
                            <span wire:loading wire:target="retryFailedVendorCommunications">Retrying...</span>
                        </button>
                        <button
                            type="button"
                            wire:click="processQueuedVendorCommunications"
                            wire:loading.attr="disabled"
                            wire:target="processQueuedVendorCommunications"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100 disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="processQueuedVendorCommunications">Process Queued</span>
                            <span wire:loading wire:target="processQueuedVendorCommunications">Processing...</span>
                        </button>
                    </div>
                </div>
            @endif

            @php
                $vendorCommunicationLogs = $this->vendorCommunicationLogs;
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor Communication Logs</p>
                <label class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">
                    <span>Rows</span>
                    <select wire:model.live="vendorCommunicationPerPage" class="rounded-lg border-slate-300 text-[11px] font-semibold focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-100 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Event</th>
                            <th class="px-3 py-2 text-left font-semibold">Invoice</th>
                            <th class="px-3 py-2 text-left font-semibold">Channel</th>
                            <th class="px-3 py-2 text-left font-semibold">Recipient</th>
                            <th class="px-3 py-2 text-left font-semibold">Status</th>
                            <th class="px-3 py-2 text-left font-semibold">Created</th>
                            <th class="px-3 py-2 text-left font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($vendorCommunicationLogs as $log)
                            @php
                                $statusClass = match ($log['status']) {
                                    'sent' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'queued' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    default => 'border-slate-200 bg-slate-50 text-slate-700',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-slate-700">
                                    <p class="font-medium text-slate-800">{{ $log['event_label'] ?? str_replace('_', ' ', (string) $log['event']) }}</p>
                                    @php
                                        $audienceClass = ($log['audience'] ?? '') === 'internal_finance'
                                            ? 'bg-indigo-100 text-indigo-700'
                                            : 'bg-sky-100 text-sky-700';
                                    @endphp
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] {{ $audienceClass }}">
                                        {{ ($log['audience'] ?? '') === 'internal_finance' ? 'Internal Finance' : 'Vendor External' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $log['invoice_number'] }}</td>
                                <td class="px-3 py-2 text-slate-700 uppercase">{{ $log['channel'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $log['recipient'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase {{ $statusClass }}">
                                        {{ $log['status'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ $log['created_at'] }}</td>
                                <td class="px-3 py-2">
                                    @if ($this->canManageVendorCommunications && $log['status'] === 'failed')
                                        <button
                                            type="button"
                                            wire:click="retryVendorCommunication({{ (int) $log['id'] }})"
                                            class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Retry
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-xs text-slate-500">No communication logs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($vendorCommunicationLogs->total() > 0)
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        Showing {{ $vendorCommunicationLogs->firstItem() ?? 0 }}-{{ $vendorCommunicationLogs->lastItem() ?? 0 }} of {{ $vendorCommunicationLogs->total() }}
                    </p>
                    {{ $vendorCommunicationLogs->links() }}
                </div>
            @endif
        </div>
    @endif

    @include('livewire.vendors.partials.vendor-form-modal')
    @include('livewire.vendors.partials.vendor-invoice-modal')
    @include('livewire.vendors.partials.vendor-payment-modal')
    @include('livewire.vendors.partials.vendor-void-invoice-modal')
</div>
