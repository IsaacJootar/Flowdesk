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

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Payment Proof (Optional)</span>
                            <input type="file" wire:model="newPaymentAttachments" multiple class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                            @error('newPaymentAttachments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @foreach ($errors->get('newPaymentAttachments.*') as $messages)
                                @foreach ($messages as $message)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @endforeach
                            @endforeach
                        </label>
                        <div wire:loading wire:target="newPaymentAttachments" class="mt-2 text-xs font-medium text-slate-600">
                            Uploading...
                        </div>
                        @if (! empty($newPaymentAttachments))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($newPaymentAttachments as $file)
                                    @if ($file)
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-600">
                                            {{ $file->getClientOriginalName() }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

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
