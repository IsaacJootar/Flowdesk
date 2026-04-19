<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="expenses"
        title="Expenses"
        description="A record of all approved and processed spend across your organisation — submitted receipts, out-of-pocket claims, and reimbursements."
        :bullets="[
            'Expenses are created automatically when a spend request is approved and paid.',
            'Filter by department, period, or category to analyse your spending patterns.',
            'Export to CSV for use in your accounting software.',
        ]"
    />
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="expense-feedback-success-{{ $feedbackKey }}"
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
                wire:key="expense-feedback-error-{{ $feedbackKey }}"
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

    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Expense code, title, vendor"
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
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor</span>
                <select wire:model.live="vendorFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All vendors</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Source</span>
                <select wire:model.live="sourceFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All sources</option>
                    <option value="direct">Direct</option>
                    <option value="from_request">From Request</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Method</span>
                <select wire:model.live="paymentMethodFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All methods</option>
                    @foreach ($paymentMethods as $paymentMethod)
                        <option value="{{ $paymentMethod }}">{{ ucfirst($paymentMethod) }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-3 grid gap-3 lg:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Date From</span>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Date To</span>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    <option value="posted">Posted</option>
                    <option value="void">Void</option>
                </select>
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">All records are company scoped and captured in Naira.</p>
            <div class="inline-flex items-center gap-2">
                <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span>Rows</span>
                    <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
                @if ($this->canManage)
                    <button
                        type="button"
                        wire:click="openCreateModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateModal"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="openCreateModal">Record Expense</span>
                        <span wire:loading wire:target="openCreateModal">Opening...</span>
                    </button>
                @else
                    <p class="text-xs text-slate-500">
                        Read-only access.
                        {{ $this->createExpenseUnavailableReason ?: 'Expense actions are restricted by company expense controls.' }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,dateFrom,dateTo,vendorFilter,departmentFilter,sourceFilter,paymentMethodFilter,statusFilter,perPage,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading expenses...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Expense</th>
                            <th class="px-4 py-3 text-left font-semibold">Source</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold">Payment</th>
                            <th class="px-4 py-3 text-left font-semibold">Date</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($expenses as $expense)
                            <tr wire:key="expense-{{ $expense->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $expense->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $expense->expense_code }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Spend Type: {{ \App\Enums\AccountingCategory::labelFor($expense->accounting_category_key) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($expense->is_direct)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Direct</span>
                                        <p class="mt-1 text-xs text-slate-500">Posted in Expenses</p>
                                    @else
                                        <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">From Request</span>
                                        @if ($expense->request)
                                            <a href="{{ route('requests.index', ['open_request_id' => (int) $expense->request_id]) }}" class="mt-1 block text-xs font-semibold text-sky-700 hover:underline">
                                                {{ $expense->request->request_code }}
                                            </a>
                                        @else
                                            <p class="mt-1 text-xs text-slate-500">Linked request</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->vendor?->name ?? 'Unlinked' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="font-medium text-slate-800">NGN {{ number_format($expense->amount) }}</p>
                                    <p class="text-xs text-slate-500">{{ $expense->payment_method ? ucfirst($expense->payment_method) : 'Not specified' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($expense->expense_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $expense->status === 'void' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ ucfirst($expense->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="openViewModal({{ $expense->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openViewModal({{ $expense->id }})"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="openViewModal({{ $expense->id }})" class="inline-flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <span>View</span>
                                            </span>
                                            <span wire:loading wire:target="openViewModal({{ $expense->id }})">Opening...</span>
                                        </button>
                                        @if ($expense->status !== 'void')
                                            @can('update', $expense)
                                                <button type="button" wire:click="openEditModal({{ $expense->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <path d="M12 20h9"></path>
                                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                                        </svg>
                                                        <span>Edit</span>
                                                    </span>
                                                </button>
                                            @endcan

                                            @can('void', $expense)
                                                <button
                                                    type="button"
                                                    wire:click="openVoidModal({{ $expense->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openVoidModal({{ $expense->id }})"
                                                    class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-70"
                                                >
                                                    <span wire:loading.remove wire:target="openVoidModal({{ $expense->id }})" class="inline-flex items-center gap-1.5">
                                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <circle cx="12" cy="12" r="9"></circle>
                                                            <path d="M7 7l10 10"></path>
                                                        </svg>
                                                        <span>Void</span>
                                                    </span>
                                                    <span wire:loading wire:target="openVoidModal({{ $expense->id }})">Opening...</span>
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No expenses recorded yet. Expenses appear here automatically when approved spend requests are paid out. You can also log out-of-pocket claims manually.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>

    @if ($showFormModal)
        <div wire:click="closeFormModal" class="fixed left-0 right-0 bottom-0 top-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-sky-700">
                                Expense Entry
                            </span>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $isEditing ? 'Edit Expense' : 'Record Expense' }}</h2>
                            <p class="text-sm text-slate-500">Expense posting permissions are controlled by your organization expense policy.</p>
                        </div>
                        <button type="button" wire:click="closeFormModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="save" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
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
                                <span class="mb-1 block text-sm font-medium text-slate-700">Expense Date</span>
                                <input type="date" wire:model.defer="form.expense_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('form.expense_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vendor (Optional)</p>
                            <label class="mb-2 block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Search Vendor</span>
                                <input type="text" wire:model.live.debounce.300ms="vendorPickerSearch" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Type vendor name">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Vendor</span>
                                <select wire:model.defer="form.vendor_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">No vendor linked</option>
                                    @foreach ($vendorPickerOptions as $vendorOption)
                                        <option value="{{ $vendorOption->id }}">{{ $vendorOption->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.vendor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Title</span>
                                <input type="text" wire:model.defer="form.title" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="What was paid for?">
                                @error('form.title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Amount (NGN)</span>
                                <input type="number" min="1" step="1" wire:model.defer="form.amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="e.g. 120000">
                                @error('form.amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Payment Method</span>
                                <select wire:model.defer="form.payment_method" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Not specified</option>
                                    @foreach ($paymentMethods as $paymentMethod)
                                        <option value="{{ $paymentMethod }}">{{ ucfirst($paymentMethod) }}</option>
                                    @endforeach
                                </select>
                                @error('form.payment_method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Spend Type</span>
                                <select wire:model.defer="form.accounting_category_key" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select spend type</option>
                                    @foreach ($accountingCategories as $category)
                                        <option value="{{ $category['key'] }}">{{ $category['label'] }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">This tells accounting what the money was for.</p>
                                @error('form.accounting_category_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Paid By (Optional)</span>
                                <select wire:model.defer="form.paid_by_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Unspecified</option>
                                    @foreach ($users as $userOption)
                                        <option value="{{ $userOption->id }}">{{ $userOption->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.paid_by_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Description (Optional)</span>
                                <textarea wire:model.defer="form.description" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                                @error('form.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21.44 11.05l-8.49 8.49a6 6 0 01-8.49-8.49l8.49-8.49a4 4 0 115.66 5.66l-8.5 8.49a2 2 0 11-2.82-2.83l7.78-7.78"></path>
                                    </svg>
                                    <span>Attachments (Optional)</span>
                                </p>
                                <p class="text-xs text-slate-500">At least one receipt is recommended for audit traceability.</p>
                            </div>
                            <div class="mt-2">
                                <input type="file" wire:model="newAttachments" multiple class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                @error('newAttachments.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div wire:loading.flex wire:target="newAttachments" class="mt-2 text-xs font-medium text-slate-600">
                                Uploading attachment(s)...
                            </div>
                            @if (! empty($newAttachments))
                                <ul class="mt-2 space-y-1 text-xs text-slate-500">
                                    @foreach ($newAttachments as $attachmentFile)
                                        <li>{{ $attachmentFile->getClientOriginalName() }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="analyzeReceiptAttachments"
                                    wire:loading.attr="disabled"
                                    wire:target="analyzeReceiptAttachments"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 3v3"></path>
                                        <path d="M12 18v3"></path>
                                        <path d="M3 12h3"></path>
                                        <path d="M18 12h3"></path>
                                        <path d="M6.3 6.3l2.1 2.1"></path>
                                        <path d="M15.6 15.6l2.1 2.1"></path>
                                        <path d="M17.7 6.3l-2.1 2.1"></path>
                                        <path d="M8.4 15.6l-2.1 2.1"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="analyzeReceiptAttachments">Use Flow Agent</span>
                                    <span wire:loading wire:target="analyzeReceiptAttachments">Analyzing Receipts...</span>
                                </button>
                                @if ($receiptAgentGeneratedAt)
                                    <span class="text-xs text-slate-500">Receipt Agent updated {{ $receiptAgentGeneratedAt }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                Use Flow Agent to analyze uploaded receipts and suggest vendor, date, amount, and reference fields.
                            </p>

                            @if ($showReceiptAgentPanel)
                                <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Receipt Agent</p>
                                            <p class="mt-1 text-xs text-slate-700">{{ $receiptAgentSummary }}</p>
                                        </div>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                            Confidence {{ $receiptAgentConfidence }}%
                                        </span>
                                    </div>

                                    @if ($receiptOcrNotice)
                                        <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-800">
                                            {{ $receiptOcrNotice }}
                                        </div>
                                    @endif

                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700">
                                            <span class="font-semibold">Vendor:</span>
                                            {{ $receiptSuggestionFields['vendor_id'] ? 'Matched' : 'Not detected' }}
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700">
                                            <span class="font-semibold">Expense Date:</span>
                                            {{ $receiptSuggestionFields['expense_date'] ?: 'Not detected' }}
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700">
                                            <span class="font-semibold">Amount:</span>
                                            {{ $receiptSuggestionFields['amount'] ? 'NGN '.number_format((int) $receiptSuggestionFields['amount']) : 'Not detected' }}
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700">
                                            <span class="font-semibold">Reference:</span>
                                            {{ $receiptSuggestedReference ?: 'Not detected' }}
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700 sm:col-span-2">
                                            <span class="font-semibold">Category Hint:</span>
                                            {{ $receiptSuggestedCategory ? ucwords(str_replace('_', ' ', $receiptSuggestedCategory)) : 'Not detected' }}
                                        </div>
                                    </div>

                                    @if ($receiptAgentSignals !== [])
                                        <ul class="mt-2 space-y-1 text-xs text-slate-500">
                                            @foreach ($receiptAgentSignals as $signal)
                                                <li>{{ $signal['source'] }} - {{ $signal['message'] }}</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="dismissReceiptSuggestions"
                                            class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            Dismiss
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="applyReceiptSuggestions"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                        >
                                            Apply Suggestions
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($duplicateWarning)
                            <div class="rounded-xl border {{ $duplicateRisk === 'hard' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4">
                                <p class="text-sm font-semibold {{ $duplicateRisk === 'hard' ? 'text-red-800' : 'text-amber-800' }}">Duplicate Guard</p>
                                <p class="mt-1 text-xs {{ $duplicateRisk === 'hard' ? 'text-red-700' : 'text-amber-700' }}">{{ $duplicateWarning }}</p>
                                @if ($duplicateMatches !== [])
                                    <div class="mt-2 space-y-1">
                                        @foreach ($duplicateMatches as $match)
                                            <p class="text-xs {{ $duplicateRisk === 'hard' ? 'text-red-700' : 'text-amber-700' }}">
                                                {{ $match['expense_code'] }} | {{ $match['expense_date'] ?: '-' }} | NGN {{ number_format((int) $match['amount']) }} | {{ $match['title'] }}
                                            </p>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($duplicateRisk === 'soft')
                                    <label class="mt-3 inline-flex items-center gap-2 text-xs font-medium text-amber-800">
                                        <input type="checkbox" wire:model.live="duplicateOverride" class="rounded border-amber-300 text-amber-700 focus:ring-amber-500">
                                        <span>I reviewed possible duplicates and want to continue.</span>
                                    </label>
                                @endif
                                @error('duplicateOverride')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div class="sticky bottom-0 -mx-6 mt-4 flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <p wire:loading.flex wire:target="newAttachments" class="text-xs font-medium text-amber-700">
                                Receipt upload in progress. Wait for upload to finish before posting.
                            </p>
                            <div class="ml-auto flex items-center gap-3">
                                <button type="button" wire:click="closeFormModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,newAttachments" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                    <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Update Expense' : 'Post Expense' }}</span>
                                    <span wire:loading wire:target="save">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showViewModal && $viewExpense)
        <div wire:click="closeViewModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                                Expense Details
                            </span>
                            <h3 class="mt-1 text-xl font-semibold text-slate-900">{{ $viewExpense['title'] }}</h3>
                            <p class="text-sm text-slate-500">{{ $viewExpense['expense_code'] }}</p>
                        </div>
                        <button type="button" wire:click="closeViewModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <div class="space-y-4 p-6">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Amount</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">NGN {{ number_format($viewExpense['amount']) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Status</p>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $viewExpense['status'] === 'void' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ ucfirst($viewExpense['status']) }}
                                </span>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Source</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $viewExpense['source_label'] === 'Direct' ? 'bg-slate-100 text-slate-700' : 'bg-sky-100 text-sky-700' }}">
                                    {{ $viewExpense['source_label'] }}
                                </span>
                                @if ($viewExpense['source_request_url'])
                                    <a href="{{ $viewExpense['source_request_url'] }}" class="text-sm font-semibold text-sky-700 hover:underline">
                                        {{ $viewExpense['source_request_code'] ?: 'Open Request' }}
                                    </a>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-slate-700">{{ $viewExpense['source_description'] }}</p>
                            @if ($viewExpense['source_request_title'])
                                <p class="mt-1 text-xs text-slate-500">{{ $viewExpense['source_request_title'] }}</p>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Department</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['department'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Vendor</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['vendor'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Payment Method</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['payment_method'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Spend Type</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['accounting_category_label'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Expense Date</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['expense_date'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Created By</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['created_by'] }}</dd></div>
                                <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Paid By</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['paid_by'] }}</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Description</p>
                            <p class="mt-2 text-sm text-slate-800">{{ $viewExpense['description'] }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21.44 11.05l-8.49 8.49a6 6 0 01-8.49-8.49l8.49-8.49a4 4 0 115.66 5.66l-8.5 8.49a2 2 0 11-2.82-2.83l7.78-7.78"></path>
                                    </svg>
                                    <span>Attachments</span>
                                </p>
                                <span class="text-xs text-slate-500">{{ count($viewExpense['attachments']) }} file(s)</span>
                            </div>
                            <div class="space-y-2">
                                @forelse ($viewExpense['attachments'] as $attachment)
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-sm font-medium text-slate-800">{{ $attachment['original_name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $attachment['mime_type'] }} - {{ $attachment['file_size_kb'] }} KB - {{ $attachment['uploaded_at'] }}</p>
                                        <a href="{{ $this->attachmentDownloadUrlById($attachment['id']) }}" target="_blank" class="mt-2 inline-block text-xs font-semibold text-slate-700 underline">
                                            Open Attachment
                                        </a>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No attachments uploaded for this expense.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                        <button type="button" wire:click="closeViewModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                        @if ($this->canEditSelectedExpense && $viewExpense['status'] !== 'void')
                            <button type="button" wire:click="openEditModal({{ $viewExpense['id'] }})" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Edit Expense
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showVoidModal)
        <div wire:click="closeVoidModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full max-w-lg rounded-2xl border border-rose-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                        <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-rose-700">Void Expense</span>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Confirm Void Action</h3>
                        <p class="mt-1 text-sm text-slate-600">This will mark the expense as void and keep it in history.</p>
                        </div>
                        <button type="button" wire:click="closeVoidModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Reason</span>
                        <textarea wire:model.defer="voidReason" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-400 focus:ring-rose-300"></textarea>
                        @error('voidReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <div class="mt-5 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        <button type="button" wire:click="closeVoidModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="submitVoidExpense"
                            wire:loading.attr="disabled"
                            wire:target="submitVoidExpense"
                            class="rounded-xl border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-70"
                            style="background-color:#0f172a;border-color:#0f172a;color:#ffffff;"
                        >
                            <span wire:loading.remove wire:target="submitVoidExpense">Void Expense</span>
                            <span wire:loading wire:target="submitVoidExpense">Voiding...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
