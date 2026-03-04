<div wire:init="loadData" class="space-y-6">
    <section class="fd-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tenant Execution</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Payout Ready Queue</h2>
                <p class="mt-1 text-sm text-slate-600">Single workspace for requests that are approved and waiting for payout execution.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('execution.health') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">
                    View Execution Health
                </a>
                <a href="{{ route('execution.help') }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">
                    Help / Usage Guide
                </a>
            </div>
        </div>
    </section>

    @if ($feedbackMessage)
        <div wire:key="payout-ready-feedback-ok-{{ $feedbackKey }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ $feedbackMessage }}
        </div>
    @endif

    @if ($feedbackError)
        <div wire:key="payout-ready-feedback-error-{{ $feedbackKey }}" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $feedbackError }}
        </div>
    @endif

    @if (! $readyToLoad)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @for ($i = 0; $i < 5; $i++)
                <div class="fd-card animate-pulse p-5">
                    <div class="mb-2 h-3 w-28 rounded bg-slate-200"></div>
                    <div class="h-7 w-14 rounded bg-slate-200"></div>
                </div>
            @endfor
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Total Waiting</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) ($summary['total'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Ready</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['ready'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sky-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Queued</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['queued'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Processing</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['processing'] ?? 0)) }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]">Failed</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($summary['failed'] ?? 0)) }}</p>
            </div>
        </section>

        <section class="fd-card p-4">
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <label for="payout-ready-status-filter" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Status</label>
                    <select id="payout-ready-status-filter" wire:model.live="statusFilter" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all">All waiting states</option>
                        <option value="ready">Ready to queue</option>
                        <option value="queued">Queued</option>
                        <option value="processing">Processing</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label for="payout-ready-search" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Search</label>
                    <input
                        id="payout-ready-search"
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by request code or title"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
            </div>
        </section>

        <section class="fd-card p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Requests Waiting for Payout</h3>
                    <p class="text-xs text-slate-500">Rows leave this queue automatically after payout settles or is reversed.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                            <th class="px-3 py-2">Request</th>
                            <th class="px-3 py-2">Raised By</th>
                            <th class="px-3 py-2">Final Approver</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Request Status</th>
                            <th class="px-3 py-2">Execution State</th>
                            <th class="px-3 py-2">Condition</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $request)
                            @php
                                $attempt = $request->payoutExecutionAttempt;
                                $amount = (float) ($request->approved_amount ?: $request->amount ?: 0);
                                $currency = strtoupper((string) ($request->currency ?: 'NGN'));
                                $executionState = $attempt ? str_replace('_', ' ', (string) $attempt->execution_status) : 'not queued';
                                $isProcessing = $attempt && in_array((string) $attempt->execution_status, ['processing', 'webhook_pending'], true);
                                $isFailedRow = (string) $request->status === 'failed' || ($attempt && (string) $attempt->execution_status === 'failed');
                            @endphp
                            <tr class="border-b border-slate-100 align-top">
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-slate-900">{{ $request->request_code }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $request->title }}</p>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $request->requester?->name ?? '-' }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $this->finalApproverName($request) }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ number_format($amount, 2) }} {{ $currency }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        {{ str_replace('_', ' ', (string) $request->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $executionState }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $this->pipelineCondition($request) }}</td>
                                <td class="px-3 py-3 text-right">
                                    @if ($canRunPayoutActions)
                                        <button
                                            type="button"
                                            wire:click="runPayoutNow({{ (int) $request->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="runPayoutNow({{ (int) $request->id }})"
                                            class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" style="background-color:#334155;border-color:#334155;color:#ffffff;"
                                        >
                                            <span wire:loading.remove wire:target="runPayoutNow({{ (int) $request->id }})">{{ $isProcessing ? 'Re-check' : ($isFailedRow ? 'Rerun Payout' : 'Run Payout') }}</span>
                                            <span wire:loading wire:target="runPayoutNow({{ (int) $request->id }})">Running...</span>
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-500">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">No payout-ready requests in your tenant queue.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        </section>
    @endif
</div>