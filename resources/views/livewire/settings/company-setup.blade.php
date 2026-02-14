<div class="mx-auto max-w-3xl space-y-6">
    <div class="fd-card p-6">
        <h2 class="text-lg font-semibold text-slate-900">Set Up Your Company</h2>
        <p class="mt-1 text-sm text-slate-500">This creates your company, a default General department, and assigns your role as owner.</p>

        <form wire:submit="save" class="mt-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="name">Company name</label>
                <input id="name" type="text" wire:model.defer="name" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Flowdesk Ltd">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="slug">Slug</label>
                    <input id="slug" type="text" wire:model.defer="slug" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="flowdesk-ltd">
                    @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="industry">Industry</label>
                    <input id="industry" type="text" wire:model.defer="industry" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Financial Services">
                    @error('industry')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input id="email" type="email" wire:model.defer="email" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="admin@company.com">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="phone">Phone</label>
                    <input id="phone" type="text" wire:model.defer="phone" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="+234...">
                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="currency_code">Currency</label>
                    <input id="currency_code" type="text" wire:model.defer="currency_code" class="mt-1 block w-full rounded-xl border-slate-300 text-sm uppercase focus:border-slate-500 focus:ring-slate-500" maxlength="3" placeholder="NGN">
                    @error('currency_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="timezone">Timezone</label>
                    <input id="timezone" type="text" wire:model.defer="timezone" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Africa/Lagos">
                    @error('timezone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="address">Address</label>
                <textarea id="address" wire:model.defer="address" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Company address"></textarea>
                @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-70" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Complete Setup</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
