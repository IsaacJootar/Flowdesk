<div class="space-y-6">
    <x-module-explainer
        key="operations"
        title="Operations Overview"
        description="Your command centre for the day-to-day running of Flowdesk — approvals needing action, pending payables, month-end close, and system health at a glance."
        :bullets="[
            'See everything that needs your attention across all modules without switching tabs.',
            'Jump directly into Approvals, Expense Handoff, Vendor Payables, or Month-End Close from here.',
            'Bottlenecks and overdue items are highlighted so you know where to focus first.',
        ]"
    />
    <section class="fd-card border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Operations Overview</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Operations Overview</h2>
                <p class="mt-1 text-sm text-slate-600">Review approvals, vendor payables, payment readiness, and month-end close from one operations view.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Payments Ready to Send</a>
                <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Purchase Order Management</a>
                <a href="{{ route('treasury.reconciliation') }}" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Bank Reconciliation</a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-4">
        <a href="{{ route('operations.approval-desk') }}" class="fd-card block border border-indigo-200 bg-indigo-50 p-5 transition hover:bg-indigo-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Approvals</p>
            <h3 class="mt-2 text-lg font-semibold text-indigo-900">Approvals Overview</h3>
            <p class="mt-2 text-sm text-indigo-800">Pending approvals, overdue items, and returned requests with one next action per row.</p>
        </a>

        <a href="{{ route('operations.expense-handoff') }}" class="fd-card block border border-emerald-200 bg-emerald-50 p-5 transition hover:bg-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Expense Handoff</p>
            <h3 class="mt-2 text-lg font-semibold text-emerald-900">Payment to Expense Review</h3>
            <p class="mt-2 text-sm text-emerald-800">Settled payouts waiting for a linked expense record or a logged exception.</p>
        </a>

        <a href="{{ route('operations.vendor-payables-desk') }}" class="fd-card block border border-amber-200 bg-amber-50 p-5 transition hover:bg-amber-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Payables</p>
            <h3 class="mt-2 text-lg font-semibold text-amber-900">Vendor Payables</h3>
            <p class="mt-2 text-sm text-amber-800">Open invoices, part-paid balances, blocked payment steps, and failed payment retries.</p>
        </a>

        <a href="{{ route('operations.period-close-desk') }}" class="fd-card block border border-rose-200 bg-rose-50 p-5 transition hover:bg-rose-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Month-End</p>
            <h3 class="mt-2 text-lg font-semibold text-rose-900">Month-End Close</h3>
            <p class="mt-2 text-sm text-rose-800">Close-readiness checklist for bank reconciliation, purchase order issues, payment retries, and audit flags.</p>
        </a>
    </section>
</div>
