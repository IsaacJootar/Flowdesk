<div class="space-y-5">
    @if ($feedbackMessage || $feedbackError)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3200)"
            x-show="show"
            x-transition.opacity.duration.250ms
            wire:key="tenant-plan-feedback-{{ $feedbackKey }}"
            class="pointer-events-none fixed z-[90]"
            style="right: 16px; top: 72px; width: 360px; max-width: calc(100vw - 24px);"
        >
            <div class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg {{ $feedbackError ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                {{ $feedbackError ?: $feedbackMessage }}
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Plan & Modules</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">Configure plan, billing state, and module entitlements.</p>
        </div>
        <a
            href="{{ route('platform.tenants') }}"
            class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
            <span aria-hidden="true">&larr;</span>
            <span>Back to Tenants</span>
        </a>
    </div>

    @include('livewire.platform.partials.tenant-section-tabs', ['company' => $company, 'tenantContextRoute' => 'platform.tenants.plan-entitlements'])

    <form wire:submit.prevent="save" class="fd-card space-y-4 p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Plan</span>
                <select wire:model.defer="planForm.plan_code" class="w-full rounded-xl border-slate-300 text-sm">
                    @foreach ($plans as $planCode => $plan)
                        <option value="{{ $planCode }}">{{ $plan['label'] ?? ucfirst((string) $planCode) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Billing Status</span>
                <select wire:model.defer="planForm.subscription_status" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="current">Current</option>
                    <option value="grace">Grace</option>
                    <option value="overdue">Overdue</option>
                    <option value="suspended">Suspended</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Seat Limit (Optional)</span>
                <input type="number" min="1" wire:model.defer="planForm.seat_limit" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Leave blank for unlimited">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Starts At</span>
                <input type="date" wire:model.defer="planForm.starts_at" class="w-full rounded-xl border-slate-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Ends At</span>
                <input type="date" wire:model.defer="planForm.ends_at" class="w-full rounded-xl border-slate-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Grace Until</span>
                <input type="date" wire:model.defer="planForm.grace_until" class="w-full rounded-xl border-slate-300 text-sm">
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Billing Reference</span>
                <input type="text" wire:model.defer="planForm.billing_reference" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Notes</span>
                <input type="text" wire:model.defer="planForm.notes" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Optional">
            </label>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-600">Apply plan defaults to modules and seat policy, then adjust as needed.</p>
                <button type="button" wire:click="applyPlanDefaults" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Apply Plan Defaults</button>
            </div>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.requests_enabled" class="rounded border-slate-300"> <span>Requests</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.expenses_enabled" class="rounded border-slate-300"> <span>Expenses</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.vendors_enabled" class="rounded border-slate-300"> <span>Vendors</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.budgets_enabled" class="rounded border-slate-300"> <span>Budgets</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.assets_enabled" class="rounded border-slate-300"> <span>Assets</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.reports_enabled" class="rounded border-slate-300"> <span>Reports</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.communications_enabled" class="rounded border-slate-300"> <span>Communications</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.ai_enabled" class="rounded border-slate-300"> <span>AI</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.fintech_enabled" class="rounded border-slate-300"> <span>Fintech</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.procurement_enabled" class="rounded border-slate-300"> <span>Procurement</span></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="entitlementsForm.treasury_enabled" class="rounded border-slate-300"> <span>Treasury</span></label>
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                <span wire:loading.remove wire:target="save">Save Plan & Modules</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
</div>





