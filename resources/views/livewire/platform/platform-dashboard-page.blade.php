<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Platform Control Center</p>
        <p class="mt-1 text-sm text-slate-600">Global operations overview for all Flowdesk tenant organizations.</p>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @if (! $readyToLoad)
            @for ($i = 0; $i < 5; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-3 h-4 w-24 rounded bg-slate-200"></div>
                    <div class="h-8 w-16 rounded bg-slate-200"></div>
                </div>
            @endfor
        @else
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Tenants</p>
                <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) $stats['tenants']) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Active</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ number_format((int) $stats['active']) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Suspended</p>
                <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) $stats['suspended']) }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Billing Overdue</p>
                <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) $stats['overdue']) }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Platform Users</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) $stats['platform_users']) }}</p>
            </div>
        @endif
    </section>

    <div class="grid gap-4 md:grid-cols-2">
        <a href="{{ route('platform.tenants') }}" class="fd-card block p-5 transition hover:border-slate-300">
            <p class="text-sm font-semibold text-slate-900">Tenant / Org Management</p>
            <p class="mt-1 text-sm text-slate-500">Manage lifecycle, plans, entitlements, and manual billing records.</p>
        </a>
        <a href="{{ route('platform.users') }}" class="fd-card block p-5 transition hover:border-slate-300">
            <p class="text-sm font-semibold text-slate-900">Platform Users</p>
            <p class="mt-1 text-sm text-slate-500">Assign global roles for platform owner, billing admin, and ops admin.</p>
        </a>
    </div>
</div>

