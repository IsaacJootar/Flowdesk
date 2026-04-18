<div class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Purchase Order Guide</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Purchase Order Guide</h2>
                <p class="mt-1 text-sm text-slate-600">How approved requests, purchase orders, receipts, and invoices become ready for payment.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Open Purchase Order Workspace<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">Payments Ready to Send<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">End-to-End Purchase Order Workflow</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Approved request is converted to a Purchase Order.</li>
            <li>Purchase Order is issued and goods or services are received.</li>
            <li>Vendor invoice is linked to the Purchase Order.</li>
            <li>Three-way match result is checked against the order, receipt, and invoice.</li>
            <li>Open match issues are resolved or waived by authorized roles.</li>
            <li>The request becomes ready to send for payment.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Payment Blocking Conditions</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>No linked vendor invoice on the Purchase Order.</li>
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
