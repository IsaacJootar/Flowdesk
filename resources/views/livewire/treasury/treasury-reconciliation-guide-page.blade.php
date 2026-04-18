<div class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reconciliation Guide</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Daily Bank Reconciliation Guide</h2>
                <p class="mt-1 text-sm text-slate-600">Daily guide for matching bank statement lines, reviewing unresolved items, and closing the day cleanly.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('treasury.reconciliation') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Open Reconciliation<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('treasury.reconciliation-exceptions') }}" class="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Open Unresolved Items<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Daily Workflow</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Select bank account and active statement scope.</li>
            <li>Import statement file for the day.</li>
            <li>Run auto-reconcile to classify matched and unmatched lines.</li>
            <li>Review unmatched lines and open issues in the same workspace.</li>
            <li>Resolve or waive issues with clear notes. If two-person review is enabled, another authorized user must confirm the decision.</li>
            <li>Use close-day checklist to confirm readiness before signoff.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Incident Handling (Short SOP)</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>If import fails, validate CSV columns and row limits, then retry import.</li>
            <li>If unmatched volume spikes, run auto-reconcile once after import and inspect references/descriptions on top lines.</li>
            <li>If issues persist, resolve/waive with precise notes and escalate policy blockers to owner/finance approvers.</li>
            <li>If payment runs are stuck in processing, open Payment Runs and verify provider state before close-day.</li>
            <li>If close-day checklist remains pending, do not sign off; capture incident notes and continue triage.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Escalation Notes</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>Always include statement reference, line reference, and issue code in handoff messages.</li>
            <li>When waiving issues, document why risk is acceptable and who approved it.</li>
            <li>Use the incident history and audit trail for post-close review.</li>
        </ul>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Reference</h3>
        <p class="mt-2 text-sm text-slate-700">Detailed markdown runbook: <span class="font-semibold">FLOWDESK_TREASURY_DAILY_RECONCILIATION_DESK_USAGE.md</span>.</p>
    </section>
</div>
