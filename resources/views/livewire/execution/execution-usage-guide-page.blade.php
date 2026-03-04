<div class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Help</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Payout Money Movement Guide</h2>
                <p class="mt-1 text-sm text-slate-600">Operational guide for how requests move from final approval to payout outcomes in Flowdesk.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">
                    Open Payout Ready Queue
                </a>
                <a href="{{ route('execution.health') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">
                    Open Execution Health
                </a>
            </div>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">End-to-End Payout Workflow</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Requester submits spend request and approval chain runs.</li>
            <li>Last required approver approves final step.</li>
            <li>Request moves to <span class="font-semibold">approved_for_execution</span>.</li>
            <li>System auto-attempts payout queueing with tenant provider + procurement gate checks.</li>
            <li>If queueing passes, payout attempt is created and request becomes <span class="font-semibold">execution_queued</span>.</li>
            <li>Processor starts execution and request becomes <span class="font-semibold">execution_processing</span>.</li>
            <li>Provider/adapters/webhooks resolve outcome: settled, failed, reversed, skipped, or webhook pending.</li>
            <li>Failed rows remain visible in payout queue so finance can <span class="font-semibold">Rerun Payout</span>.</li>
            <li>Settled/reversed outcomes leave payout waiting queue and are tracked in logs/audit trails.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Queue Status Meanings</h3>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Ready</p>
                <p class="mt-1"><span class="font-semibold">approved_for_execution</span>: fully approved and waiting to be queued/processed.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Queued</p>
                <p class="mt-1"><span class="font-semibold">execution_queued</span>: payout attempt exists and is waiting to process.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Processing</p>
                <p class="mt-1"><span class="font-semibold">execution_processing</span>: processor is running or waiting on provider lifecycle transitions.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Failed</p>
                <p class="mt-1"><span class="font-semibold">failed</span>: payout failed and should be retried after checks (provider/config/state).</p>
            </article>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Action Labels in Queue</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li><span class="font-semibold">Run Payout</span>: first-time run for non-failed rows.</li>
            <li><span class="font-semibold">Rerun Payout</span>: retry action for failed rows.</li>
            <li><span class="font-semibold">Re-check</span>: row already in processing/webhook-pending path.</li>
        </ul>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Operator Notes</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>Queue handling is operationally time-based but not guaranteed strict global FIFO in all retry/multi-worker scenarios.</li>
            <li>Final Approver can show `-` when seeded/system rows were moved to execution states without a full approval actor trail.</li>
            <li>If queueing is blocked, check procurement gate, provider configuration, and execution mode for the tenant.</li>
            <li>Use Execution Health for incident context and escalation posture.</li>
        </ul>
    </section>
</div>