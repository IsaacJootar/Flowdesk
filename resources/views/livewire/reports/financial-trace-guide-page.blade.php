<div class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Reports</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Budget to Payment Guide</h2>
                <p class="mt-1 text-sm text-slate-600">How to read the money trail from budget check to audit evidence.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.financial-trace') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Open Budget to Payment Trace<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                <a href="{{ route('requests.index') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open Requests<svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
            </div>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Trace Sequence</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li><span class="font-semibold">Budget Check</span>: request amount is compared with the department budget period.</li>
            <li><span class="font-semibold">Request</span>: requester, department, vendor, amount, and request status anchor the trail.</li>
            <li><span class="font-semibold">Approval</span>: approval rows show who reviewed the request and whether all required steps are complete.</li>
            <li><span class="font-semibold">Order / Commitment</span>: purchase orders and active commitments show money reserved before payment.</li>
            <li><span class="font-semibold">Payment</span>: payout attempt shows the execution method, provider, reference, and status.</li>
            <li><span class="font-semibold">Expense Record</span>: linked expense shows the accounting record for actual spend.</li>
            <li><span class="font-semibold">Bank Match</span>: reconciliation proves the bank statement line matches the payment or expense.</li>
            <li><span class="font-semibold">Audit Events</span>: activity logs and tenant audit events show who changed or processed key records.</li>
        </ol>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Report Columns</h3>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Budget</p>
                <p class="mt-1">Within Budget means the request fits the budget snapshot. No Budget Found means the request needs budget setup or review.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Approval</p>
                <p class="mt-1">The fraction shows approved steps compared with total approval rows currently recorded.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Order / Commitment</p>
                <p class="mt-1">Purchase orders show vendor purchase intent. Commitments show budget exposure reserved by procurement.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Payment</p>
                <p class="mt-1">Bank Transfer is the active vendor payment path. Wallet and card flows stay outside this trace until those products are enabled.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Expense</p>
                <p class="mt-1">Expense records are the accounting side of spend. A settled payment without an expense needs finance follow-up.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Bank Match</p>
                <p class="mt-1">Matched means the bank line has been linked to the payment or expense. Open issues need bank review.</p>
            </article>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Trace Notes</h3>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">No budget was found</p>
                <p class="mt-1">Create or correct the department budget for the request period, then recheck policy context on the request.</p>
            </article>
            <article class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                <p class="font-semibold">Settled without expense</p>
                <p class="mt-1">Create or link the expense record so reports reflect actual spend.</p>
            </article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">Settled without bank match</p>
                <p class="mt-1">Run reconciliation and match the payment reference or linked expense to the bank statement line.</p>
            </article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">PO without commitment</p>
                <p class="mt-1">Review procurement issuance and budget commitment posting for the purchase order.</p>
            </article>
        </div>
    </section>

    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Where to Fix Issues</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li><span class="font-semibold">Budget issue</span>: open Budgets and confirm the department period is active.</li>
            <li><span class="font-semibold">Approval issue</span>: open the request details and review the approval timeline.</li>
            <li><span class="font-semibold">Purchase order issue</span>: open Purchase Order Management and check order issuance or match status.</li>
            <li><span class="font-semibold">Payment issue</span>: open Payments Ready to Send or Payment Provider Health.</li>
            <li><span class="font-semibold">Expense issue</span>: open the request and create or verify the linked expense record.</li>
            <li><span class="font-semibold">Bank match issue</span>: open Bank Reconciliation and resolve unmatched items.</li>
        </ul>
    </section>
</div>
