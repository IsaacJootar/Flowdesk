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
