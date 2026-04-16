<div wire:init="loadData" class="space-y-6">
    <section class="fd-card border border-sky-200 bg-sky-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Organization Workspace</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Organization Admin Desk</h2>
                <p class="mt-1 text-sm text-slate-700">Set up departments, assign team members, and configure approval chains — all in one place.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('departments.index') }}" class="inline-flex items-center rounded-lg border border-sky-200 bg-white px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">Departments</a>
                <a href="{{ route('team.index') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Team</a>
                <a href="{{ route('approval-workflows.index') }}" class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Approval Workflows</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <label for="organization-admin-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search Administration</label>
                <input id="organization-admin-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Department, team member, workflow scope" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="rounded-xl border border-sky-200 bg-white px-3 py-2 text-xs text-slate-600">
                One clear next action per row keeps organization operations simple and auditable.
            </div>
        </div>
    </section>

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @for ($i = 0; $i < 4; $i++)
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
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Departments Needing Head</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['department_coverage'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-indigo-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Team Assignment Gaps</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['team_assignment'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Approval Chain Gaps</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['workflow_governance'] ?? 0)) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.14em]">Total Workload</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($desk['summary']['workload_total'] ?? 0)) }}</p></div>
        </section>

        <section class="fd-card border border-sky-200 bg-sky-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Progress Overview</p>
            <p class="mt-1 text-sm text-slate-700">Current bottleneck: {{ $desk['summary']['bottleneck_label'] ?? 'No blockers' }} ({{ number_format((int) ($desk['summary']['bottleneck_count'] ?? 0)) }})</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="flex h-full w-full">
                @foreach (($desk['summary']['segments'] ?? []) as $segment)
                    @if ((int) ($segment['count'] ?? 0) > 0)
                        @php
                            $segmentClass = match ((string) ($segment['tone'] ?? 'slate')) {
                                'sky' => 'bg-sky-400',
                                'indigo' => 'bg-indigo-400',
                                'amber' => 'bg-amber-400',
                                default => 'bg-slate-400',
                            };
                        @endphp
                        <div class="{{ $segmentClass }}" style="width: {{ max(0.5, (float) ($segment['percent'] ?? 0)) }}%"></div>
                    @endif
                @endforeach
            </div></div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            @foreach ([
                'department_coverage' => ['title' => 'Departments Missing Head', 'hint' => 'Assign ownership so department approvals and escalations are explicit.', 'tone' => 'sky'],
                'team_assignment' => ['title' => 'Team Assignment Gaps', 'hint' => 'Fix missing department/reporting lines for active users.', 'tone' => 'indigo'],
                'workflow_governance' => ['title' => 'Approval Chain Gaps', 'hint' => 'Make sure each workflow area has an approval chain assigned.', 'tone' => 'amber'],
            ] as $laneKey => $laneMeta)
                @php
                    $laneBorder = match ($laneMeta['tone']) {
                        'sky' => 'border-sky-200 bg-sky-50',
                        'indigo' => 'border-indigo-200 bg-indigo-50',
                        'amber' => 'border-amber-200 bg-amber-50',
                        default => 'border-slate-200 bg-slate-50',
                    };
                    $actionTone = match ($laneMeta['tone']) {
                        'sky' => 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100',
                        'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
                        'amber' => 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100',
                        default => 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
                    };
                @endphp
                <div class="fd-card border p-4 {{ $laneBorder }}">
                    <h3 class="text-sm font-semibold text-slate-900">{{ $laneMeta['title'] }}</h3>
                    <p class="mb-3 text-xs text-slate-500">{{ $laneMeta['hint'] }}</p>

                    <div class="space-y-2">
                        @forelse (($desk['lanes'][$laneKey] ?? []) as $row)
                            <div class="rounded-xl border border-white/70 bg-white px-3 py-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $row['ref'] }} <span class="text-xs font-medium text-slate-500">· {{ $row['status'] }}</span></p>
                                <p class="mt-1 text-xs text-slate-600">{{ $row['title'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['meta'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['context'] }}</p>
                                <div class="mt-2 text-right">
                                    <a href="{{ $row['next_action_url'] }}" class="inline-flex rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $actionTone }}">{{ $row['next_action_label'] }}</a>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">Nothing needs attention here right now.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>
    @endif
</div>
