<div class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Procurement Help</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Procurement Release Guide</h2>
                <p class="mt-1 text-sm text-slate-600">How procurement records move to payout release readiness in one operational flow.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Open Release Desk</a>
                <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">Payments Ready to Send</a>
            </div>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">End-to-End Procurement Release Workflow</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Approved request is converted to PO.</li>
            <li>PO is issued and goods/services are received.</li>
            <li>Vendor invoice is linked to PO.</li>
            <li>3-way match result is evaluated (matched/overridden passes gate).</li>
            <li>Open match issues are resolved or waived by authorized roles.</li>
            <li>Procurement gate passes and request can proceed to payout run.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Release Blocking Conditions</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>No linked vendor invoice on PO.</li>
            <li>No 3-way match result for linked invoice.</li>
            <li>Match status not passed (`pending` or `mismatch`).</li>
            <li>Open procurement match issues still unresolved.</li>
        </ul>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Operator Roles</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>Owner/Finance: primary release operators.</li>
            <li>Manager: operational support where policy allows.</li>
            <li>Auditor: visibility and verification trail (typically no override action).</li>
        </ul>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Reference</h3>
        <p class="mt-2 text-sm text-slate-700">Full written runbook: <span class="font-semibold">FLOWDESK_PROCUREMENT_RELEASE_DESK_USAGE.md</span>.</p>
    </section>
</div>
