<div class="space-y-6">
    <x-module-explainer
        key="settings"
        title="Settings"
        description="Configure how Flowdesk works for your organisation — approval rules, spending limits, notification preferences, payment providers, and more."
        :bullets="[
            'Changes here affect the entire organisation, so only admins and finance owners can edit settings.',
            'Each section is independent — you can configure only what you need.',
            'All setting changes are logged in your audit trail.',
        ]"
    />
    <section class="fd-card border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Settings</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Settings</h2>
                <p class="mt-1 text-sm text-slate-600">Open a section to manage company setup, requests, controls, payment providers, or access.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('settings.company.setup') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Company Setup</a>
                <a href="{{ route('departments.index') }}" class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">Departments</a>
                <a href="{{ route('team.index') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Team</a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Visible Controls</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) $totalControls) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Enabled</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) $enabledControls) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Blocked by Plan</p>
            <p class="mt-2 text-2xl font-semibold">{{ number_format((int) $blockedControls) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]">Scope</p>
            <p class="mt-2 text-sm font-semibold">Organization owner controls</p>
            <p class="mt-1 text-xs text-slate-600">These settings update your workspace.</p>
        </div>
    </section>

    @foreach ($sections as $section)
        @php
            $sectionContainerClass = match ((string) ($section['tone'] ?? 'slate')) {
                'sky' => 'border-sky-200 bg-sky-50',
                'indigo' => 'border-indigo-200 bg-indigo-50',
                'amber' => 'border-amber-200 bg-amber-50',
                'rose' => 'border-rose-200 bg-rose-50',
                default => 'border-slate-200 bg-slate-50',
            };

            $statusEnabledClass = match ((string) ($section['tone'] ?? 'slate')) {
                'sky' => 'border-sky-200 bg-sky-100 text-sky-800',
                'indigo' => 'border-indigo-200 bg-indigo-100 text-indigo-800',
                'amber' => 'border-amber-200 bg-amber-100 text-amber-800',
                'rose' => 'border-rose-200 bg-rose-100 text-rose-800',
                default => 'border-slate-200 bg-slate-100 text-slate-800',
            };

            $actionClass = match ((string) ($section['tone'] ?? 'slate')) {
                'sky' => 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100',
                'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
                'amber' => 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100',
                'rose' => 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100',
                default => 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
            };
        @endphp

        <section class="fd-card border p-5 {{ $sectionContainerClass }}">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-700">{{ $section['label'] }}</h3>
                    <p class="mt-1 text-xs text-slate-600">{{ $section['description'] }}</p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($section['cards'] as $card)
                    <article class="rounded-xl border border-white/70 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $card['label'] }}</h4>
                            <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $card['enabled'] ? $statusEnabledClass : 'border-rose-200 bg-rose-50 text-rose-700' }}">{{ $card['status_label'] }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-600">{{ $card['description'] }}</p>

                        <div class="mt-4">
                            @if ($card['enabled'])
                                <a href="{{ route($card['route']) }}" class="inline-flex rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $actionClass }}">{{ $card['action_label'] }}</a>
                            @else
                                <span class="inline-flex rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">{{ $card['action_label'] }}</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <article class="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-500">No controls available in this section.</article>
                @endforelse
            </div>
        </section>
    @endforeach

    <section class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
        Module status snapshot:
        Requests {{ $moduleFlags['requests'] ? 'enabled' : 'disabled' }} |
        Communications {{ $moduleFlags['communications'] ? 'enabled' : 'disabled' }} |
        Expenses {{ $moduleFlags['expenses'] ? 'enabled' : 'disabled' }} |
        Vendors {{ $moduleFlags['vendors'] ? 'enabled' : 'disabled' }} |
        Assets {{ $moduleFlags['assets'] ? 'enabled' : 'disabled' }} |
        Procurement {{ $moduleFlags['procurement'] ? 'enabled' : 'disabled' }} |
        Treasury {{ $moduleFlags['treasury'] ? 'enabled' : 'disabled' }} |
        Payment Providers {{ $moduleFlags['fintech'] ? 'enabled' : 'disabled' }}.
    </section>
</div>
