<div wire:init="loadData" class="space-y-5">
    @if ($feedbackMessage)
        <div
            wire:key="expense-feedback-{{ $feedbackKey }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3500)"
            x-show="show"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ $feedbackMessage }}
        </div>
    @endif

    @if ($feedbackError)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $feedbackError }}
        </div>
    @endif

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

            <div class="grid gap-3 sm:grid-cols-3">
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
                        <span wire:loading.remove wire:target="openCreateModal">Record Expense</span>
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
            <div wire:loading.flex wire:target="search,dateFrom,dateTo,vendorFilter,departmentFilter,paymentMethodFilter,statusFilter,gotoPage,previousPage,nextPage" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
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
                                            wire:target="openViewModal"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            <span wire:loading.remove wire:target="openViewModal">View</span>
                                            <span wire:loading wire:target="openViewModal">Opening...</span>
                                        </button>
                                        @if ($this->canManage && $expense->status !== 'void')
                                            <button type="button" wire:click="openEditModal({{ $expense->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="openVoidModal({{ $expense->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="openVoidModal"
                                                class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="openVoidModal">Void</span>
                                                <span wire:loading wire:target="openVoidModal">Opening...</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
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
                            <h2 class="text-lg font-semibold text-slate-900">{{ $isEditing ? 'Edit Expense' : 'Record Expense' }}</h2>
                            <p class="text-sm text-slate-500">Finance and owner can post direct expense payments without approval dependencies.</p>
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
                                <p class="text-sm font-medium text-slate-700">Attachments (Optional)</p>
                                <p class="text-xs text-slate-500">At least one receipt is recommended for audit traceability.</p>
                            </div>
                            <div class="mt-2">
                                <input type="file" wire:model="newAttachments" multiple class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                @error('newAttachments.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
                            <button type="button" wire:click="closeFormModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,newAttachments" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="save,newAttachments">{{ $isEditing ? 'Update Expense' : 'Post Expense' }}</span>
                                <span wire:loading wire:target="save,newAttachments">Saving...</span>
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
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Expense Details</p>
                            <h3 class="mt-1 text-xl font-semibold text-slate-900">{{ $viewExpense['title'] }}</h3>
                            <p class="text-sm text-slate-500">{{ $viewExpense['expense_code'] }}</p>
                        </div>
                        <button type="button" wire:click="closeViewModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
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
                                    <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $viewExpense['status'] === 'void' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
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
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Created By</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['created_by'] }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-[0.1em] text-slate-500">Paid By</dt><dd class="mt-1 font-medium text-slate-800">{{ $viewExpense['paid_by'] }}</dd></div>
                                </dl>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Description</p>
                                <p class="mt-2 text-sm text-slate-800">{{ $viewExpense['description'] }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-sm font-semibold text-slate-800">Attachments</p>
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
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                        <button type="button" wire:click="closeViewModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                        @if ($this->canManage && $viewExpense['status'] !== 'void')
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
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 p-4">
            <div class="flex min-h-full items-center justify-center">
                <div class="w-full max-w-lg rounded-2xl border border-rose-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-600">Void Expense</p>
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

