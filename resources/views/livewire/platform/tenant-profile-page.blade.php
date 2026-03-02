<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Profile</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">{{ $company->slug }} - {{ $company->email ?: 'no email' }}</p>
        </div>
        <a
            href="{{ route('platform.tenants') }}"
            class="inline-flex h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
            <span aria-hidden="true">&larr;</span>
            <span>Back to Tenants</span>
        </a>
    </div>

    @include('livewire.platform.partials.tenant-section-tabs', ['company' => $company, 'tenantContextRoute' => 'platform.tenants.show'])

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Lifecycle</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ ucfirst((string) ($company->lifecycle_status ?: 'active')) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Plan</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ ucfirst((string) ($company->subscription?->plan_code ?? 'pilot')) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Billing State</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ ucfirst((string) ($company->subscription?->subscription_status ?? 'current')) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Users</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) $company->users()->count()) }}</p>
        </div>
    </section>

    <div class="fd-card p-5">
        <h3 class="text-base font-semibold text-slate-900">Tenant Identity</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Name</p>
                <p class="text-sm text-slate-800">{{ $company->name }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Slug</p>
                <p class="text-sm text-slate-800">{{ $company->slug }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Email</p>
                <p class="text-sm text-slate-800">{{ $company->email ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Phone</p>
                <p class="text-sm text-slate-800">{{ $company->phone ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Industry</p>
                <p class="text-sm text-slate-800">{{ $company->industry ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Currency / Timezone</p>
                <p class="text-sm text-slate-800">{{ strtoupper((string) ($company->currency_code ?: 'NGN')) }} / {{ $company->timezone ?: 'Africa/Lagos' }}</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Address</p>
            <p class="text-sm text-slate-800">{{ $company->address ?: '-' }}</p>
        </div>
    </div>
</div>




