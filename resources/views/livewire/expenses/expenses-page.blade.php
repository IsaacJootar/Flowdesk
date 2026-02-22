<div wire:init="loadData" class="space-y-5">
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
                class="pointer-events-auto rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-4 xl:grid-cols-[1fr_auto] xl:items-end">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Payment Method</span>
                    <select wire:model.live="paymentMethodFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All methods</option>
                        @foreach ($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod }}">{{ ucfirst($paymentMethod) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-4">
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
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Source</span>
                    <select wire:model.live="sourceFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="all">All sources</option>
                        <option value="direct">Direct</option>
                        <option value="request">Request-linked</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">All records are company scoped and captured in Naira.</p>
            <div class="inline-flex items-center gap-2">
                @if ($this->canManage)
                    <button
                        type="button"
                        wire:click="openCreateModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateModal"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="openCreateModal" class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 5v14"></path>
                                <path d="M5 12h14"></path>
                            </svg>
                            <span>Record Expense</span>
                        </span>
                        <span wire:loading wire:target="openCreateModal">Opening...</span>
                    </button>
                @else
                    <p class="text-xs text-slate-500">Read-only access. Only owner/finance can record or void expenses.</p>
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
            <div wire:loading.flex wire:target="search,dateFrom,dateTo,vendorFilter,departmentFilter,paymentMethodFilter,statusFilter,sourceFilter,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading expenses...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Expense</th>
                            <th class="px-4 py-3 text-left font-semibold">Department</th>
                            <th class="px-4 py-3 text-left font-semibold">Vendor</th>
                            <th class="px-4 py-3 text-left font-semibold">Payment</th>
                            <th class="px-4 py-3 text-left font-semibold">Date</th>
                            <th class="px-4 py-3 text-left font-semibold">Source</th>
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
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->department?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->vendor?->name ?? 'Unlinked' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="font-medium text-slate-800">NGN {{ number_format($expense->amount) }}</p>
                                    <p class="text-xs text-slate-500">{{ $expense->payment_method ? ucfirst($expense->payment_method) : 'Not specified' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($expense->expense_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $expense->is_direct ? 'bg-slate-200 text-slate-700' : 'bg-indigo-50 text-indigo-700' }}">
                                        {{ $expense->is_direct ? 'Direct' : 'Request-linked' }}
                                    </span>
                                    @if (! $expense->is_direct && $expense->request_id)
                                        <p class="mt-1 text-xs text-slate-500">REQ-{{ $expense->request_id }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $expense->status === 'void' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ ucfirst($expense->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="openViewModal({{ $expense->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openViewModal"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="openViewModal" class="inline-flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <span>View</span>
                                            </span>
                                            <span wire:loading wire:target="openViewModal">Opening...</span>
                                        </button>
                                        @if ($this->canManage && $expense->status !== 'void')
                                            <button type="button" wire:click="openEditModal({{ $expense->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                <span class="inline-flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                                    </svg>
                                                    <span>Edit</span>
                                                </span>
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="openVoidModal({{ $expense->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="openVoidModal"
                                                class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="openVoidModal" class="inline-flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="9"></circle>
                                                        <path d="M7 7l10 10"></path>
                                                    </svg>
                                                    <span>Void</span>
                                                </span>
                                                <span wire:loading wire:target="openVoidModal">Opening...</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No expenses found. @if ($this->canManage)Record your first expense to start vendor payment tracking.@endif
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
        <div class="fixed left-0 right-0 bottom-0 top-16 z-40 overflow-y-auto bg-slate-900/40 p-4">
            <div class="flex min-h-full items-start justify-center py-6">
                <div class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Expense Form
                            </span>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $isEditing ? 'Edit Expense' : 'Record Expense' }}</h2>
                            <p class="text-sm text-slate-500">Finance and owner can post direct expense payments without approval dependencies.</p>
                        </div>
                        <button type="button" wire:click="closeFormModal" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                            <span>Close</span>
                        </button>
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

                        @if ($feedbackError)
                            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ $feedbackError }}
                            </div>
                        @endif

                        @if ($duplicateWarning)
                            <div class="rounded-xl border px-4 py-3 text-sm {{ $duplicateWarning['risk'] === 'hard' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                                <p class="font-semibold">
                                    {{ $duplicateWarning['risk'] === 'hard' ? 'Exact duplicate found' : 'Possible duplicate found' }}
                                </p>
                                <p class="mt-1 text-xs {{ $duplicateWarning['risk'] === 'hard' ? 'text-rose-700/90' : 'text-amber-800/90' }}">
                                    Match basis: same amount, same date, and same vendor.
                                </p>

                                @if (! empty($duplicateWarning['matches']))
                                    <ul class="mt-2 space-y-1 text-xs">
                                        @foreach ($duplicateWarning['matches'] as $match)
                                            <li class="rounded-lg border border-black/10 bg-white/70 px-2.5 py-1.5">
                                                <span class="font-semibold">{{ $match['expense_code'] }}</span>
                                                <span class="mx-1">-</span>
                                                <span>{{ $match['title'] }}</span>
                                                <span class="mx-1">-</span>
                                                <span>NGN {{ number_format($match['amount']) }}</span>
                                                <span class="mx-1">-</span>
                                                <span>{{ $match['expense_date'] ? \Illuminate\Support\Carbon::parse($match['expense_date'])->format('M d, Y') : '-' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($duplicateWarning['risk'] === 'soft')
                                    <label class="mt-3 inline-flex items-center gap-2">
                                        <input type="checkbox" wire:model.defer="form.duplicate_override" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                        <span class="text-xs font-medium text-slate-800">Override duplicate warning and continue</span>
                                    </label>
                                @endif
                            </div>
                        @endif

                        @error('form.duplicate_override')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror

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
                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Source</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Expense Source</span>
                                    <select wire:model.live="form.source_mode" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                        <option value="direct">Direct expense</option>
                                        <option value="request">Request-linked expense</option>
                                    </select>
                                    @error('form.source_mode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Request Reference @if (($form['source_mode'] ?? 'direct') === 'request')<span class="text-red-600">*</span>@endif</span>
                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        wire:model.defer="form.request_reference"
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-400"
                                        placeholder="e.g. 1042"
                                        @if (($form['source_mode'] ?? 'direct') !== 'request') disabled @endif
                                    >
                                    @error('form.request_reference')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                            </div>
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
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21.44 11.05 12.25 20.24a5.5 5.5 0 0 1-7.78-7.78L13 4a3.5 3.5 0 0 1 5 5l-8.49 8.49a1.5 1.5 0 1 1-2.12-2.12L15 7.76"></path>
                                    </svg>
                                    <span>Attachments (Optional)</span>
                                </p>
                                <p class="text-xs text-slate-500">At least one receipt is recommended for audit traceability.</p>
                            </div>
                            <div class="mt-2">
                                <input type="file" wire:model="newAttachments" multiple class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                @error('newAttachments.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div wire:loading wire:target="newAttachments" class="mt-2 text-xs text-slate-600">
                                Uploading...
                            </div>
                            @if (! empty($newAttachments))
                                <ul class="mt-2 space-y-1 text-xs text-slate-500">
                                    @foreach ($newAttachments as $attachmentFile)
                                        <li>{{ $attachmentFile->getClientOriginalName() }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closeFormModal" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                                <span>Cancel</span>
                            </button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,newAttachments" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M20 6 9 17l-5-5"></path>
                                    </svg>
                                    <span>{{ $isEditing ? 'Update Expense' : 'Post Expense' }}</span>
                                </span>
                                <span wire:loading wire:target="save">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showViewModal && $viewExpense)
        <div class="fixed left-0 right-0 bottom-0 top-16 z-50 overflow-y-auto bg-slate-900/40 p-4">
            <div class="flex min-h-full items-start justify-center py-8">
                <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Expense Details
                            </span>
                            <h3 class="mt-1 text-xl font-semibold text-slate-900">{{ $viewExpense['title'] }}</h3>
                            <p class="text-sm text-slate-500">{{ $viewExpense['expense_code'] }}</p>
                        </div>
                        <div class="flex items-center gap-4 whitespace-nowrap">
                            <button type="button" wire:click="closeViewModal" class="inline-flex items-center gap-1.5 p-0 text-xs font-medium text-slate-900 hover:text-slate-700">
                                <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                                <span>Close</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-5 p-6 lg:grid-cols-[1.1fr_1fr]">
                        <div class="space-y-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Amount</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">NGN {{ number_format($viewExpense['amount']) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Status</p>
                                    <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $viewExpense['status'] === 'void' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ ucfirst($viewExpense['status']) }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4">
                                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Department</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['department'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Vendor</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['vendor'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Payment Method</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['payment_method'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Expense Date</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['expense_date'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Source</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['source'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Linked Request</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['request_reference'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Created By</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['created_by'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Paid By</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['paid_by'] }}</dd></div>
                                </dl>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Description</p>
                                <p class="mt-2 text-sm text-slate-800">{{ $viewExpense['description'] }}</p>
                            </div>

                            @if ($viewExpense['status'] === 'void')
                                <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-red-600">Void Audit Trail</p>
                                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                        <div><dt class="text-xs uppercase tracking-[0.1em] text-red-500">Voided By</dt><dd class="mt-1 font-medium text-red-700">{{ $viewExpense['voided_by'] }}</dd></div>
                                        <div><dt class="text-xs uppercase tracking-[0.1em] text-red-500">Voided At</dt><dd class="mt-1 font-medium text-red-700">{{ $viewExpense['voided_at'] ?? '-' }}</dd></div>
                                        <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-[0.1em] text-red-500">Reason</dt><dd class="mt-1 font-medium text-red-700">{{ $viewExpense['void_reason'] }}</dd></div>
                                    </dl>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-sky-700">
                                        Attachments
                                    </span>
                                    <span class="text-xs text-slate-500">{{ count($viewExpense['attachments']) }} file(s)</span>
                                </div>
                                <div class="space-y-2">
                                    @forelse ($viewExpense['attachments'] as $attachment)
                                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                            <p class="text-sm font-medium text-slate-800">{{ $attachment['original_name'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $attachment['mime_type'] }} - {{ $attachment['file_size_kb'] }} KB - {{ $attachment['uploaded_at'] }}</p>
                                            <a href="{{ $this->attachmentDownloadUrlById($attachment['id']) }}" target="_blank" class="mt-2 inline-flex h-7 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <path d="M21.44 11.05 12.25 20.24a5.5 5.5 0 0 1-7.78-7.78L13 4a3.5 3.5 0 0 1 5 5l-8.49 8.49a1.5 1.5 0 1 1-2.12-2.12L15 7.76"></path>
                                                </svg>
                                                <span>Open Attachment</span>
                                            </a>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">No attachments uploaded for this expense.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                        <button type="button" wire:click="closeViewModal" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                            <span>Close</span>
                        </button>
                        @if ($this->canManage && $viewExpense['status'] !== 'void')
                            <button type="button" wire:click="openEditModal({{ $viewExpense['id'] }})" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                </svg>
                                <span>Edit Expense</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showVoidModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4">
            <div class="flex min-h-full items-center justify-center">
                <div class="w-full max-w-lg rounded-2xl border border-rose-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                        <span class="inline-flex items-center rounded-full border border-red-200 bg-red-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-red-700">Void Expense</span>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Confirm Void Action</h3>
                        <p class="mt-1 text-sm text-slate-600">This will mark the expense as void and keep it in history.</p>
                        </div>
                        <button type="button" wire:click="closeVoidModal" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                            <span>Close</span>
                        </button>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Reason</span>
                        <textarea wire:model.defer="voidReason" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-400 focus:ring-rose-300"></textarea>
                        @error('voidReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <div class="mt-5 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        <button type="button" wire:click="closeVoidModal" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                            <span>Cancel</span>
                        </button>
                        <button
                            type="button"
                            wire:click="submitVoidExpense"
                            wire:loading.attr="disabled"
                            wire:target="submitVoidExpense"
                            class="rounded-xl border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-70"
                            style="background-color:#0f172a;border-color:#0f172a;color:#ffffff;"
                        >
                            <span wire:loading.remove wire:target="submitVoidExpense" class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M7 7l10 10"></path>
                                </svg>
                                <span>Void Expense</span>
                            </span>
                            <span wire:loading wire:target="submitVoidExpense">Voiding...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

