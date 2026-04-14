<div wire:init="loadData" class="space-y-6">
    <x-module-explainer
        key="vendors"
        title="Vendor Directory"
        description="Your central record of all approved suppliers — bank details, contact info, payment history, and compliance status in one place."
        :bullets="[
            'Only vendors in this directory can receive payments from Flowdesk.',
            'Bank details are verified before a vendor is approved.',
            'All payments made to each vendor are logged and searchable here.',
        ]"
    />
    <section class="fd-card border border-amber-200 bg-amber-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Vendor Directory</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Vendor Management Workspace</h2>
                <p class="mt-1 text-sm text-slate-700">Single page for vendor profile quality, invoice follow-up, and payables handoff actions.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('vendors.registry') }}" class="inline-flex items-center rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Vendor Registry</a>
                <a href="{{ route('vendors.reports') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Vendor Reports</a>
                @if ($canOpenPayablesDesk)
                    <a href="{{ route('operations.vendor-payables-desk') }}" class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Vendor Payables Desk</a>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <label for="vendor-command-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Desk</label>
                <input id="vendor-command-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Vendor, invoice, request code" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs text-slate-600">
                One next action per row keeps vendor operations direct and easy to hand over.
            </div>
        </div>
    </section>

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @for ($i = 0; $i < 5; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-2 h-3 w-32 rounded bg-slate-200"></div>
                    <div class="h-7 w-20 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @elseif (! $desk['enabled'])
        <section class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $desk['disabled_reason'] }}
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Total Vendors</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['total_vendors'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Active Vendors</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['active_vendors'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Open Invoices</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['open_invoices'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Blocked Handoffs</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['blocked_handoff'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Failed Retries</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['failed_retries'] ?? 0)) }}</p></div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Part-Paid Invoices</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['part_paid'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Overdue Invoices</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['overdue'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Total Workload</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['workload_total'] ?? 0)) }}</p></div>
        </section>

        <section class="fd-card border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Vendor Workload Progress</p>
            <p class="mt-1 text-sm text-slate-700">Current bottleneck: {{ $desk['summary']['bottleneck_label'] ?? 'No blockers' }} ({{ number_format((int) ($desk['summary']['bottleneck_count'] ?? 0)) }})</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="flex h-full w-full">
                @foreach (($desk['summary']['segments'] ?? []) as $segment)
                    @if ((int) ($segment['count'] ?? 0) > 0)
                        @php
                            $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                'sky' => 'bg-sky-400',
                                'amber' => 'bg-amber-400',
                                'rose' => 'bg-rose-400',
                                'indigo' => 'bg-indigo-400',
                                default => 'bg-slate-400',
                            };
                        @endphp
                        <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                    @endif
                @endforeach
            </div></div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            @foreach ([
                'profile_hygiene' => ['title' => 'Profile Hygiene', 'hint' => 'Vendors with missing bank/contact details.', 'tone' => 'sky'],
                'invoice_follow_up' => ['title' => 'Invoice & Statement Follow-up', 'hint' => 'Open and overdue invoices awaiting payment progress.', 'tone' => 'amber'],
                'blocked_handoff' => ['title' => 'Blocked Payables Handoff', 'hint' => 'Vendor-linked requests blocked by procurement gate.', 'tone' => 'rose'],
                'failed_retries' => ['title' => 'Failed Payout Retries', 'hint' => 'Vendor-linked payouts that failed and require rerun.', 'tone' => 'indigo'],
            ] as $laneKey => $laneMeta)
                @php
                    $laneBorder = match ($laneMeta['tone']) {
                        'sky' => 'border-sky-200 bg-sky-50',
                        'amber' => 'border-amber-200 bg-amber-50',
                        'rose' => 'border-rose-200 bg-rose-50',
                        'indigo' => 'border-indigo-200 bg-indigo-50',
                        default => 'border-slate-200 bg-slate-50',
                    };
                    $actionTone = match ($laneMeta['tone']) {
                        'sky' => 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100',
                        'amber' => 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100',
                        'rose' => 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100',
                        'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
                        default => 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
                    };
                @endphp
                <div class="fd-card border p-4 {{ $laneBorder }}">
                    <h3 class="text-sm font-semibold text-slate-900">{{ $laneMeta['title'] }}</h3>
                    <p class="mb-3 text-xs text-slate-500">{{ $laneMeta['hint'] }}</p>

                    <div class="space-y-2">
                        @forelse (($desk['lanes'][$laneKey] ?? []) as $row)
                            <div class="rounded-xl border border-white/70 bg-white px-3 py-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} <span class="text-xs font-medium text-slate-500">? {{ $row['status'] }}</span></p>
                                <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['meta'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['context'] }}</p>
                                <div class="mt-2 text-right">
                                    <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $actionTone }}">{{ $row['next_action_label'] }}</a>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">No vendors in this category yet. Vendors must be added and approved before payments can be sent to them.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>
    @endif
</div>

