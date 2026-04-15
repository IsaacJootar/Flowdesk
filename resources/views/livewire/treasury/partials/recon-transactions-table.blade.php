<div class="fd-card overflow-hidden">
    <div class="border-b border-slate-200 px-4 py-3">
        <h3 class="text-sm font-semibold text-slate-900">Today's Bank Transactions</h3>
        <p class="text-xs text-slate-500">Every line from your bank statement is listed here. Green means matched to a payment — amber means it still needs attention.</p>
    </div>
    @if (! $readyToLoad)
        <div class="space-y-3 p-4">
            @for ($i = 0; $i < 8; $i++)
                <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
            @endfor
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Posted</th>
                        <th class="px-4 py-3 text-left font-semibold">Reference</th>
                        <th class="px-4 py-3 text-left font-semibold">Account</th>
                        <th class="px-4 py-3 text-left font-semibold">Direction</th>
                        <th class="px-4 py-3 text-right font-semibold">Amount</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lines as $line)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600">{{ optional($line->posted_at)->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <p>{{ $line->line_reference ?: '-' }}</p>
                                <p class="text-xs text-slate-500">{{ $line->description ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $line->account?->bank_name }} | {{ $line->account?->account_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ ucfirst((string) $line->direction) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ strtoupper((string) $line->currency_code) }} {{ number_format((int) $line->amount) }}</td>
                            <td class="px-4 py-3">
                                @if ($line->is_reconciled)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Matched</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Not Matched</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No bank transactions yet. Sync via Mono or upload a CSV to see your transactions here.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">Showing {{ $lines->firstItem() ?? 0 }}-{{ $lines->lastItem() ?? 0 }} of {{ $lines->total() }}</p>
                {{ $lines->links() }}
            </div>
        </div>
    @endif
</div>
