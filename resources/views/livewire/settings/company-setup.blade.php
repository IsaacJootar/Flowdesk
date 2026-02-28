<div class="mx-auto max-w-3xl space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="company-setup-feedback-success-{{ $feedbackKey }}"
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
                wire:key="company-setup-feedback-error-{{ $feedbackKey }}"
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

    @if ($showTenantInsights)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-sky-700">Subscription</p>
                <p class="mt-1 text-lg font-semibold text-sky-900">{{ ucfirst(str_replace('_', ' ', (string) $tenantInsights['plan_code'])) }}</p>
                <p class="text-xs text-sky-700">{{ ucfirst(str_replace('_', ' ', (string) $tenantInsights['subscription_status'])) }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-indigo-700">Seat Usage</p>
                <p class="mt-1 text-lg font-semibold text-indigo-900">{{ number_format((int) $tenantInsights['active_users']) }} / {{ $tenantInsights['seat_limit'] !== null ? number_format((int) $tenantInsights['seat_limit']) : 'Unlimited' }}</p>
                <p class="text-xs text-indigo-700">{{ number_format((float) $tenantInsights['seat_utilization'], 2) }}% utilization</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-amber-700">Coverage</p>
                <p class="mt-1 text-sm font-semibold text-amber-900">Ends: {{ $tenantInsights['coverage_end'] ?: 'Not set' }}</p>
                <p class="text-xs text-amber-700">Grace until: {{ $tenantInsights['grace_until'] ?: 'Not set' }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">Modules Enabled</p>
                <p class="mt-1 text-lg font-semibold text-emerald-900">{{ number_format((int) $tenantInsights['enabled_modules']) }} / {{ number_format((int) $tenantInsights['total_modules']) }}</p>
                <p class="text-xs text-emerald-700">From your current tenant controls</p>
            </div>
        </div>
    @endif

    <div class="fd-card p-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ $isEditMode ? 'Company Settings' : 'Set Up Your Company' }}</h2>
        <p class="mt-1 text-sm text-slate-500">
            {{ $isEditMode ? 'Update core company profile and configuration details.' : 'This creates your company, a default General department, and assigns your role as admin (owner).' }}
        </p>

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
                    <span wire:loading.remove wire:target="save">{{ $isEditMode ? 'Save Changes' : 'Complete Setup' }}</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
