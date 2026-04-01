<div class="space-y-6">
    <section class="fd-card border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Unified Operations</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Operations Desks</h2>
                <p class="mt-1 text-sm text-slate-600">Choose a dedicated desk for approvals, vendor payables, or period-close readiness.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('execution.payout-ready') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Open Payout Queue</a>
                <a href="{{ route('procurement.release-desk') }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Manage Procurement</a>
                <a href="{{ route('treasury.reconciliation') }}" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Manage Treasury</a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <a href="{{ route('operations.approval-desk') }}" class="fd-card block border border-indigo-200 bg-indigo-50 p-5 transition hover:bg-indigo-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Desk 1</p>
            <h3 class="mt-2 text-lg font-semibold text-indigo-900">Approval Operations Desk</h3>
            <p class="mt-2 text-sm text-indigo-800">Pending approvals, overdue items, and returned requests with one next action per row.</p>
        </a>

        <a href="{{ route('operations.vendor-payables-desk') }}" class="fd-card block border border-amber-200 bg-amber-50 p-5 transition hover:bg-amber-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Desk 2</p>
            <h3 class="mt-2 text-lg font-semibold text-amber-900">Vendor Payables Desk</h3>
            <p class="mt-2 text-sm text-amber-800">Open invoices, part-paid balances, blocked payout steps, and failed payout retries.</p>
        </a>

        <a href="{{ route('operations.period-close-desk') }}" class="fd-card block border border-rose-200 bg-rose-50 p-5 transition hover:bg-rose-100">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Desk 3</p>
            <h3 class="mt-2 text-lg font-semibold text-rose-900">Period Close Desk</h3>
            <p class="mt-2 text-sm text-rose-800">Close-readiness checklist for treasury reconciliation, procurement issues, payout retries, and audit flags.</p>
        </a>
    </section>
</div>
