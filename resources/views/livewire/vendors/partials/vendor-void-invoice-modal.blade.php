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

