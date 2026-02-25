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

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <label class="block">
                            <span class="mb-1 inline-flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M7.5 6.5a2.5 2.5 0 015 0V10a4 4 0 11-8 0V6.5a1 1 0 112 0V10a2 2 0 104 0V6.5a.5.5 0 00-1 0V10a1 1 0 11-2 0V6.5z" />
                                </svg>
                                <span>Invoice Attachments (Optional)</span>
                            </span>
                            <input type="file" wire:model="newInvoiceAttachments" multiple class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                            @error('newInvoiceAttachments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @foreach ($errors->get('newInvoiceAttachments.*') as $messages)
                                @foreach ($messages as $message)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @endforeach
                            @endforeach
                        </label>
                        <div wire:loading wire:target="newInvoiceAttachments" class="mt-2 text-xs font-medium text-slate-600">
                            Uploading...
                        </div>
                        @if (! empty($newInvoiceAttachments))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($newInvoiceAttachments as $file)
                                    @if ($file)
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-600">
                                            {{ $file->getClientOriginalName() }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

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
