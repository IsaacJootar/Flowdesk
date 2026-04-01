<div wire:init="loadData" class="space-y-5">
    <div class="fd-card p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Platform Control Center</p>
        <p class="mt-1 text-sm text-slate-600">Global operations overview for all Flowdesk organizations.</p>
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
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Organizations</p>
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

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('platform.tenants') }}" class="block rounded-2xl border border-sky-200 bg-sky-50 p-5 transition hover:border-sky-300 hover:bg-sky-100">
            <p class="text-sm font-semibold text-sky-900">Organization Management</p>
            <p class="mt-1 text-sm text-sky-700">Manage lifecycle, plans, entitlements, and manual billing records.</p>
        </a>
        <a href="{{ route('platform.users') }}" class="block rounded-2xl border border-indigo-200 bg-indigo-50 p-5 transition hover:border-indigo-300 hover:bg-indigo-100">
            <p class="text-sm font-semibold text-indigo-900">Platform Users</p>
            <p class="mt-1 text-sm text-indigo-700">Assign global roles for platform owner, billing admin, and ops admin.</p>
        </a>
        <a href="{{ route('platform.operations.hub') }}" class="block rounded-2xl border border-emerald-200 bg-emerald-50 p-5 transition hover:border-emerald-300 hover:bg-emerald-100">
            <p class="text-sm font-semibold text-emerald-900">Operations Hub</p>
            <p class="mt-1 text-sm text-emerald-700">Single control workspace for execution operations, test checklist, incident history, and pilot rollout KPIs.</p>
        </a>
    </div>
</div>
