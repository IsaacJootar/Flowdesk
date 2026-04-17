<div class="space-y-5">
    <div class="fd-card p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Notification Recovery Guide</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Notification Recovery</h2>
                <p class="mt-2 text-sm text-slate-600">Use this guide when messages fail, stay pending, or need delivery settings checked across requests, vendors, or assets.</p>
            </div>
            <a href="{{ route('requests.communications') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Back to Notification Recovery</a>
        </div>
    </div>

    <div class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Daily Recovery Flow</h3>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
            <li>Open the <span class="font-semibold">Delivery Recovery</span> tab and set <span class="font-semibold">Display Scope</span> and <span class="font-semibold">Display Age Filter</span>.</li>
            <li>Check the module cards for failed and stuck pending messages.</li>
            <li>Review <span class="font-semibold">Channel Issues</span> and <span class="font-semibold">Recipient / Config Breakdown</span> before running retries.</li>
            <li>Use <span class="font-semibold">Retry Failed</span> to re-attempt failed rows in current scope.</li>
            <li>Use <span class="font-semibold">Process Pending</span> to process messages older than your age filter.</li>
            <li>Use row-level <span class="font-semibold">Retry now</span> when only one log needs intervention.</li>
        </ol>
    </div>

    <div class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">How to Interpret Breakdowns</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li><span class="font-semibold">Missing recipient email/phone</span>: update recipient profile or target contact before retrying.</li>
            <li><span class="font-semibold">Channel disabled or unconfigured</span>: fix organization communication settings and provider configuration first.</li>
            <li><span class="font-semibold">Provider/send error</span>: provider outage or rejected request; validate transport credentials and retry.</li>
            <li><span class="font-semibold">Unsupported channel</span>: event/channel mapping is invalid and needs configuration correction.</li>
        </ul>
    </div>

    <div class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Escalation Guidance</h3>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
            <li>If failures persist after config fixes and two retry attempts, escalate with example log IDs and module scope.</li>
            <li>If pending messages keep growing, reduce delivery load and verify scheduler/worker health for communication commands.</li>
            <li>Use Incident History and Audit Logs to correlate repeated delivery failures with recent configuration changes.</li>
        </ul>
    </div>
</div>
