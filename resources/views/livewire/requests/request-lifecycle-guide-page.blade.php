<div class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Request Lifecycle Help</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">How to Run the Lifecycle Desk</h2>
                <p class="mt-1 text-sm text-slate-600">Simple operator guide from final approval to payout dispatch and closure.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('requests.lifecycle-desk') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Open Lifecycle Desk</a>
                @if ($canOpenPayoutQueue)
                    <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Payments Ready to Send</a>
                @endif
            </div>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Lane Sequence (Left to Right)</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li><span class="font-semibold">Approved (Need PO)</span>: convert approved requests to procurement orders.</li>
            <li><span class="font-semibold">PO / Match Follow-up</span>: resolve invoice/match blockers and procurement gate issues.</li>
            <li><span class="font-semibold">Waiting Payout Dispatch</span>: request is approved_for_execution and ready to run payout.</li>
            <li><span class="font-semibold">Execution Active / Retry</span>: queued/processing rows or failed rows needing rerun.</li>
            <li><span class="font-semibold">Settled / Reversed</span>: completed outcomes for audit follow-up.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Status Meaning Quick Reference</h3>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><p class="font-semibold text-slate-900">approved</p><p class="mt-1">Approval finished in requests lane, but procurement or payout handoff still pending.</p></article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><p class="font-semibold text-slate-900">approved_for_execution</p><p class="mt-1">Ready for payout queue if procurement gate is not blocked.</p></article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><p class="font-semibold text-slate-900">execution_queued / execution_processing</p><p class="mt-1">Money movement is in active execution lifecycle.</p></article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><p class="font-semibold text-slate-900">failed / settled / reversed</p><p class="mt-1">Failed needs rerun; settled/reversed are closed outcomes.</p></article>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Operator Rules</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>Use the lifecycle desk first so all payout-prep decisions happen in one place.</li>
            <li>Use one Next Action per row to avoid scattered operations.</li>
            <li>If blocked by procurement gate, resolve match/invoice issues before retrying payout queue.</li>
            <li>For failed payout attempts, rerun from Payout Ready Queue after provider/config/state checks.</li>
        </ul>
    </section>
</div>
