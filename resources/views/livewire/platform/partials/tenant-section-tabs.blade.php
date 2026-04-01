@php
    $links = [
        ['label' => 'Profile', 'route' => 'platform.tenants.show', 'active' => 'platform.tenants.show'],
        ['label' => 'Plan & Modules', 'route' => 'platform.tenants.plan-entitlements', 'active' => 'platform.tenants.plan-entitlements'],
        ['label' => 'Billing', 'route' => 'platform.tenants.billing', 'active' => 'platform.tenants.billing'],
        ['label' => 'Execution Mode', 'route' => 'platform.tenants.execution-mode', 'active' => 'platform.tenants.execution-mode'],
        ['label' => 'Execution Policy', 'route' => 'platform.tenants.execution-policy', 'active' => 'platform.tenants.execution-policy'],
    ];

    $internalSlugs = array_values(array_unique(array_filter(array_map(
        static fn (mixed $slug): string => strtolower(trim((string) $slug)),
        (array) config('platform.internal_company_slugs', [])
    ))));

    $tenantOptions = \App\Domains\Company\Models\Company::query()
        ->when(
            $internalSlugs !== [],
            fn ($query) => $query->whereNotIn('slug', $internalSlugs)
        )
        ->orderBy('name')
        ->get(['id', 'name']);

    $currentRoute = isset($tenantContextRoute) && is_string($tenantContextRoute) && $tenantContextRoute !== ''
        ? $tenantContextRoute
        : (request()->routeIs('platform.tenants.*')
            ? (string) request()->route()?->getName()
            : 'platform.tenants.show');
@endphp

<div class="fd-card p-3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['route'], $company) }}"
                    class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ request()->routeIs($link['active']) ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <label class="block min-w-[230px]">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">Organization Context</span>
            <select
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                onchange="if (this.value) window.location.href = this.value;"
            >
                @foreach ($tenantOptions as $tenantOption)
                    <option
                        value="{{ route($currentRoute, ['company' => $tenantOption->id]) }}"
                        @selected((int) $company->id === (int) $tenantOption->id)
                    >
                        {{ $tenantOption->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>
</div>
