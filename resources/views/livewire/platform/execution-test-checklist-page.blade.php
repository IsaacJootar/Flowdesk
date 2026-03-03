<div class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Execution Test Checklist</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">UI dry-run checklist for operators</h2>
                <p class="mt-1 text-sm text-slate-600">Use this page to validate execution mode and operations workflows from the UI without relying on CLI commands.</p>
            </div>
</div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="fd-card p-5">
            <h3 class="text-sm font-semibold text-slate-900">1. Prepare tenant context</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Open <a href="{{ route('platform.tenants') }}" class="font-medium text-slate-900 underline">Tenant / Org Management</a>.</li>
                <li>Select the tenant you want to test{{ $latestTenant ? ' (latest: '.$latestTenant->name.')' : '' }}.</li>
                <li>Confirm tenant lifecycle is <span class="font-semibold">active</span>.</li>
            </ol>
        </section>

        <section class="fd-card p-5">
            <h3 class="text-sm font-semibold text-slate-900">2. Configure execution mode</h3>
            @if ($latestTenant)
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                    <li>Open <a href="{{ route('platform.tenants.execution-mode', $latestTenant) }}" class="font-medium text-slate-900 underline">Tenant Execution Mode</a>.</li>
                    <li>Set mode to <span class="font-semibold">execution_enabled</span> for execution tests.</li>
                    <li>Use <span class="font-semibold">Use manual_ops</span> quick button to set provider baseline.</li>
                    <li>Save and confirm success toast.</li>
                </ol>
            @else
                <p class="mt-3 text-sm text-slate-700">Create at least one tenant first, then return here to continue the checklist.</p>
            @endif
        </section>

        <section class="fd-card p-5">
            <h3 class="text-sm font-semibold text-slate-900">3. Trigger a pipeline record</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Use tenant billing actions (manual payment or charge action) to create billing attempts.</li>
                <li>Use a request payout flow to create payout execution attempts.</li>
                <li>If provider callbacks are available, verify webhook events are captured.</li>
            </ol>
            <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Tip: Start with <span class="font-semibold">manual_ops</span> provider for safe dry-runs.
            </p>
        </section>

        <section class="fd-card p-5">
            <h3 class="text-sm font-semibold text-slate-900">4. Operate and reconcile</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                <li>Open <a href="{{ route('platform.operations.execution') }}" class="font-medium text-slate-900 underline">Execution Operations</a>.</li>
                <li>Filter by tenant/provider/pipeline and inspect statuses.</li>
                <li>Retry failed attempts with reason.</li>
                <li>Process stuck queued records older than threshold.</li>
                <li>Reconcile webhook records and confirm status updates.</li>
            </ol>
        </section>
    </div>

    <section class="fd-card p-5" id="provider-config">
        <h3 class="text-sm font-semibold text-slate-900">Runbook: Recovery breakdown quick checks</h3>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <article id="missing-request" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Missing request</p>
                <p class="mt-1">Verify `request_payout_execution_attempts.request_id` points to a real row in `spend_requests` (including soft-deleted records).</p>
            </article>
            <article id="missing-subscription" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Missing subscription</p>
                <p class="mt-1">Verify `tenant_subscription_id` links to an active tenant subscription and the tenant/provider mode is still valid.</p>
            </article>
            <article id="state-changed" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">State changed</p>
                <p class="mt-1">The row likely moved out of queued/eligible state while recovery was running. Refresh filters and retry only queued records.</p>
            </article>
            <article id="invalid-verification" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Invalid verification</p>
                <p class="mt-1">Recheck provider webhook signature/hash config and payload format before retrying reconciliation.</p>
            </article>
            <article id="missing-linked-attempt" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 md:col-span-2">
                <p class="font-semibold text-slate-900">Missing linked attempt</p>
                <p class="mt-1">Webhook event has no linked billing/payout attempt. Confirm event payload carries attempt ID and mapping fields are stored.</p>
            </article>
        </div>
    </section>
    <section class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Pass criteria</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>Execution mode saves with a valid provider key.</li>
            <li>Billing and payout attempts transition through expected statuses.</li>
            <li>Webhook events can be reconciled from the operations center.</li>
            <li>Tenant audit events are written for manual operator actions.</li>
        </ul>
    </section>
</div>


