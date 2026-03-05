<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                <th class="px-3 py-2">Request</th>
                <th class="px-3 py-2">Context</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2 text-right">Next Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $statusTone = match ((string) ($row['next_action_tone'] ?? 'slate')) {
                        'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
                        'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
                        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        'rose' => 'border-rose-200 bg-rose-50 text-rose-800',
                        'sky' => 'border-sky-200 bg-sky-50 text-sky-800',
                        default => 'border-slate-200 bg-slate-50 text-slate-700',
                    };
                    $actionTone = match ((string) ($row['next_action_tone'] ?? 'slate')) {
                        'amber' => 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100',
                        'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100',
                        'emerald' => 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
                        'rose' => 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100',
                        'sky' => 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100',
                        default => 'border-slate-700 bg-slate-700 text-white hover:bg-slate-800',
                    };
                @endphp
                <tr class="border-b border-slate-100 align-top">
                    <td class="px-3 py-3">
                        <p class="font-semibold text-slate-900">{{ $row['ref'] ?? '-' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $row['title'] ?? '-' }}</p>
                    </td>
                    <td class="px-3 py-3 text-slate-700">
                        <p>{{ $row['meta'] ?? '-' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $row['context'] ?? '-' }}</p>
                    </td>
                    <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $row['status'] ?? '-' }}</span></td>
                    <td class="px-3 py-3 text-right">
                        <a href="{{ $row['next_action_url'] ?? '#' }}" class="inline-flex rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $actionTone }}" @if (empty($row['next_action_url'])) aria-disabled="true" @endif>{{ $row['next_action_label'] ?? 'Open' }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">No rows in this lane for current scope/filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
