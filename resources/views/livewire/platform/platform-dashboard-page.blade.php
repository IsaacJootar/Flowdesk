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
        <a href="{{ route('platform.tenants') }}" class="group block rounded-2xl border border-sky-200 bg-sky-50 p-5 transition hover:border-sky-300 hover:bg-sky-100">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-sky-900">Organization Management</p>
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-sky-300 bg-white text-sky-700 transition group-hover:translate-x-0.5 group-hover:border-sky-400 group-hover:bg-sky-100" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
            </div>
            <p class="mt-1 text-sm text-sky-700">Manage lifecycle, plans, entitlements, and manual billing records.</p>
            <p class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-sky-800">
                Open
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </p>
        </a>
        <a href="{{ route('platform.users') }}" class="group block rounded-2xl border border-indigo-200 bg-indigo-50 p-5 transition hover:border-indigo-300 hover:bg-indigo-100">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-indigo-900">Platform Users</p>
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-indigo-300 bg-white text-indigo-700 transition group-hover:translate-x-0.5 group-hover:border-indigo-400 group-hover:bg-indigo-100" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
            </div>
            <p class="mt-1 text-sm text-indigo-700">Assign global roles for platform owner, billing admin, and ops admin.</p>
            <p class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-indigo-800">
                Open
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </p>
        </a>
        <a href="{{ route('platform.operations.hub') }}" class="group block rounded-2xl border border-emerald-200 bg-emerald-50 p-5 transition hover:border-emerald-300 hover:bg-emerald-100">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-emerald-900">Operations Hub</p>
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-emerald-300 bg-white text-emerald-700 transition group-hover:translate-x-0.5 group-hover:border-emerald-400 group-hover:bg-emerald-100" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
            </div>
            <p class="mt-1 text-sm text-emerald-700">Single control workspace for execution operations, test checklist, incident history, and pilot rollout KPIs.</p>
            <p class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-emerald-800">
                Open
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </p>
        </a>
    </div>
</div>
