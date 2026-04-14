<div wire:init="loadData" class="space-y-5">
    <x-module-explainer
        key="treasury-reconciliation"
        title="Daily Bank Reconciliation"
        description="Match your bank statement lines against approved payments and flag anything that does not add up — all in one place, every day."
        :bullets="[
            'Sync today\'s bank statement automatically via Mono Connect, or upload a CSV if needed.',
            'Auto-Reconcile matches statement lines to payment runs in seconds.',
            'Unmatched lines become open issues — resolve or waive them before end-of-day sign-off.',
        ]"
        guide-route="treasury.reconciliation-help"
    />
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div wire:key="treasury-recon-success-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3200)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                {{ $feedbackMessage }}
            </div>
        @endif

        @if ($feedbackError)
            <div wire:key="treasury-recon-error-{{ $feedbackKey }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.250ms class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg">
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Manage Treasury</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Daily Bank Reconciliation</h2>
                <p class="mt-1 text-sm text-slate-600">Match your bank statement lines against approved payments, unmatched lines, issue decisions, auto-reconcile, and close-day checks.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('treasury.reconciliation-help') }}" class="inline-flex h-9 items-center rounded-lg border border-slate-700 bg-slate-700 px-3 text-xs font-semibold text-white transition hover:bg-slate-800" style="background-color:#334155;border-color:#334155;color:#ffffff;">Help / Usage Guide</a>
                <a href="{{ route('treasury.reconciliation-exceptions') }}" class="inline-flex h-9 items-center rounded-lg border border-rose-300 bg-rose-50 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Issue Queue</a>
                <a href="{{ route('treasury.payment-runs') }}" class="inline-flex h-9 items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Payment Runs</a>
                <a href="{{ route('treasury.cash-position') }}" class="inline-flex h-9 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">Cash Position</a>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Import Status</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ strtoupper((string) ($importStatus['import_status'] ?? 'not imported')) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $importStatus['statement_reference'] ?? '-' }}</p>
            <p class="text-xs text-slate-500">{{ $importStatus['imported_at'] ?: 'No import yet' }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Statement Lines</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ number_format((int) ($summary['lines'] ?? 0)) }}</p>
            <p class="text-xs text-sky-700">Reconciled {{ number_format((int) ($summary['reconciled'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Unmatched Lines</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ number_format((int) ($summary['unreconciled'] ?? 0)) }}</p>
            <p class="text-xs text-amber-700">Value {{ number_format((int) ($summary['unreconciled_value'] ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-700">Open Issues</p>
            <p class="mt-2 text-2xl font-semibold text-rose-900">{{ number_format((int) ($summary['open_exceptions'] ?? 0)) }}</p>
            <p class="text-xs text-rose-700">Resolve or waive before close-day.</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Payment Runs</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) ($summary['processing_runs'] ?? 0)) }}</p>
            <p class="text-xs text-indigo-700">Processing now | failed today {{ number_format((int) ($paymentRunSummary['failed_today'] ?? 0)) }}</p>
        </div>
    </div>

    @php
        $workloadSegments = [
            ['label' => 'Unmatched', 'count' => (int) ($summary['unreconciled'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Open Issues', 'count' => (int) ($summary['open_exceptions'] ?? 0), 'tone' => 'rose'],
            ['label' => 'Processing Runs', 'count' => (int) ($summary['processing_runs'] ?? 0), 'tone' => 'indigo'],
        ];
        $workloadTotal = (int) collect($workloadSegments)->sum('count');
        $bottleneck = collect($workloadSegments)->sortByDesc('count')->first();
        $bottleneckLabel = (string) ($bottleneck['label'] ?? 'No blockers');
        $bottleneckCount = (int) ($bottleneck['count'] ?? 0);
    @endphp

    <div class="fd-card border border-indigo-200 bg-indigo-50 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Treasury Workload Progress</p>
                <p class="mt-1 text-sm text-slate-700">Open treasury workload: <span class="font-semibold">{{ number_format($workloadTotal) }}</span></p>
                <p class="text-xs text-slate-500">Current bottleneck: {{ $bottleneckLabel }} ({{ number_format($bottleneckCount) }})</p>
            </div>
        </div>

        <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100">
            <div class="flex h-full w-full">
                @foreach ($workloadSegments as $segment)
                    @if ((int) $segment['count'] > 0)
                        @php
                            $segmentColor = match ((string) ($segment['tone'] ?? 'slate')) {
                                'amber' => 'bg-amber-400',
                                'rose' => 'bg-rose-400',
                                'indigo' => 'bg-indigo-400',
                                default => 'bg-slate-400',
                            };
                            $segmentPercent = $workloadTotal > 0
                                ? (((int) $segment['count'] / $workloadTotal) * 100)
                                : 0;
                        @endphp
                        <div class="{{ $segmentColor }}" style="width: {{ max(0.5, $segmentPercent) }}%"></div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
            @foreach ($workloadSegments as $segment)
                <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-100 px-2 py-1 text-indigo-800">
                    <span>{{ $segment['label'] }}</span>
                    <span class="font-semibold">{{ number_format((int) $segment['count']) }}</span>
                </span>
            @endforeach
        </div>
    </div>
    <div class="fd-card p-5">
        <div class="grid gap-3 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Bank Account</span>
                <select wire:model.live="selectedBankAccountId" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">Select account</option>
                    @foreach ($accounts as $account)
                        <option value="{{ (int) $account->id }}">{{ $account->bank_name }} | {{ $account->account_name }} ({{ strtoupper((string) $account->currency_code) }})</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Statement</span>
                <select wire:model.live="selectedStatementId" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">Latest</option>
                    @foreach ($statements as $statement)
                        <option value="{{ (int) $statement->id }}">{{ $statement->statement_reference }} | {{ optional($statement->created_at)->format('M d, Y H:i') }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search Line</span>
                <input type="text" wire:model.live.debounce.300ms="lineSearch" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Reference or description">
            </label>

            <div class="flex items-end justify-end">
                <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span>Rows</span>
                    <select wire:model.live="linePerPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
            <p>Rows imported: {{ number_format((int) ($importStatus['rows_imported'] ?? 0)) }} | skipped duplicates: {{ number_format((int) ($importStatus['rows_skipped'] ?? 0)) }}</p>
            <p>Latest closing balance: {{ number_format((int) ($importStatus['closing_balance'] ?? 0)) }} | Last payment run update: {{ $paymentRunSummary['last_run_at'] ?: 'N/A' }}</p>
        </div>
    </div>

    <div class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Bank Account Setup</h3>
        <p class="mt-1 text-xs text-slate-500">Add account references used for statement ingestion and reconciliation context.</p>

        @if ($errors->has('bankAccountForm.account_name') || $errors->has('bankAccountForm.bank_name') || $errors->has('bankAccountForm.currency_code'))
            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                Complete required bank account fields before saving.
            </div>
        @endif

        <div class="mt-3 grid gap-3 md:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-xs text-slate-600">Account Name</span>
                <input type="text" wire:model.defer="bankAccountForm.account_name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Operations Account">
                @error('bankAccountForm.account_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs text-slate-600">Bank Name</span>
                <input type="text" wire:model.defer="bankAccountForm.bank_name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="First City Bank">
                @error('bankAccountForm.bank_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs text-slate-600">Currency</span>
                <input type="text" maxlength="3" wire:model.defer="bankAccountForm.currency_code" class="w-full rounded-xl border-slate-300 text-sm uppercase focus:border-slate-500 focus:ring-slate-500" placeholder="NGN">
                @error('bankAccountForm.currency_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs text-slate-600">Account Reference</span>
                <input type="text" wire:model.defer="bankAccountForm.account_reference" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Bank provided ID">
                @error('bankAccountForm.account_reference')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs text-slate-600">Masked Number</span>
                <input type="text" wire:model.defer="bankAccountForm.account_number_masked" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="****1234">
                @error('bankAccountForm.account_number_masked')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </label>
            <label class="inline-flex items-center gap-2 pt-6 text-sm text-slate-700">
                <input type="checkbox" wire:model.defer="bankAccountForm.is_primary" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                Set as primary account
            </label>
        </div>

        @if ($canOperate)
            <div class="mt-3 flex justify-end">
                <button type="button" wire:click="createBankAccount" wire:loading.attr="disabled" wire:target="createBankAccount" class="rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                    <span wire:loading.remove wire:target="createBankAccount">Save Bank Account</span>
                    <span wire:loading wire:target="createBankAccount">Saving...</span>
                </button>
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Statement Import and Auto-Reconcile</h3>

        @if ($activeMonoAccount)
            {{-- Mono Connect is linked: primary sync panel --}}
            <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">Mono Connect — Live Bank Feed</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $activeMonoAccount->institution_name ?? 'Linked Bank' }} · {{ $activeMonoAccount->account_name ?? 'Account' }}</p>
                        <p class="text-xs text-slate-500">
                            Last synced: {{ $activeMonoAccount->last_synced_at ? $activeMonoAccount->last_synced_at->format('M d, Y H:i') : 'Never' }}
                            @if ($activeMonoAccount->balance_amount)
                                &nbsp;·&nbsp; Balance: NGN {{ number_format($activeMonoAccount->balance_major, 2) }}
                            @endif
                        </p>
                    </div>
                    @if ($canOperate)
                        <div class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                                <span>Days back</span>
                                <select wire:model.live="monoSyncDays" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                                    <option value="1">1</option>
                                    <option value="3">3</option>
                                    <option value="7">7</option>
                                    <option value="14">14</option>
                                    <option value="30">30</option>
                                </select>
                            </label>
                            <button type="button" wire:click="syncMonoStatement" wire:loading.attr="disabled" wire:target="syncMonoStatement" class="rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="syncMonoStatement">Sync via Mono</span>
                                <span wire:loading wire:target="syncMonoStatement">Syncing...</span>
                            </button>
                        </div>
                    @endif
                </div>
                @if ($activeMonoAccount->sync_error)
                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-700">Last sync error: {{ $activeMonoAccount->sync_error }}</p>
                @endif
            </div>

            {{-- CSV as fallback when Mono is linked --}}
            <div class="mt-4 border-t border-slate-100 pt-4">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Manual CSV Upload (fallback)</p>
                <p class="mt-1 text-xs text-slate-400">Use only if Mono sync is unavailable. Columns: posted_at, direction, amount, optional value_date/description/line_reference/currency_code/balance_after.</p>
                <div class="mt-2 grid gap-3 sm:grid-cols-[1fr,auto]">
                    <input type="file" wire:model="statementFile" accept=".csv,.txt" class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-500">
                    @if ($canOperate)
                        <button type="button" wire:click="importStatement" wire:loading.attr="disabled" wire:target="importStatement,statementFile" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-70">
                            <span wire:loading.remove wire:target="importStatement,statementFile">Upload CSV</span>
                            <span wire:loading wire:target="importStatement,statementFile">Uploading...</span>
                        </button>
                    @endif
                </div>
                @error('statementFile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Auto-reconcile always available --}}
            @if ($canOperate)
                <div class="mt-3 flex justify-end">
                    <button type="button" wire:click="runAutoReconcile" wire:loading.attr="disabled" wire:target="runAutoReconcile" class="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-70">
                        <span wire:loading.remove wire:target="runAutoReconcile">Run Auto-Reconcile</span>
                        <span wire:loading wire:target="runAutoReconcile">Running...</span>
                    </button>
                </div>
            @endif
        @else
            {{-- No Mono account linked: CSV is primary, amber nudge to link --}}
            @if ($selectedBankAccountId)
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-amber-700">Mono Connect not linked</p>
                    <p class="mt-1 text-xs text-slate-600">Link this bank account via Mono Connect to enable live transaction sync and eliminate manual CSV uploads. Contact your treasury admin to complete the widget linking flow.</p>
                </div>
            @endif

            <p class="mt-3 text-xs text-slate-500">CSV columns: posted_at, direction, amount, optional value_date/description/line_reference/currency_code/balance_after.</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-[1fr,auto,auto]">
                <input type="file" wire:model="statementFile" accept=".csv,.txt" class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700">
                @if ($canOperate)
                    <button type="button" wire:click="importStatement" wire:loading.attr="disabled" wire:target="importStatement,statementFile" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70">
                        <span wire:loading.remove wire:target="importStatement,statementFile">Import Statement</span>
                        <span wire:loading wire:target="importStatement,statementFile">Importing...</span>
                    </button>
                    <button type="button" wire:click="runAutoReconcile" wire:loading.attr="disabled" wire:target="runAutoReconcile" class="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-70">
                        <span wire:loading.remove wire:target="runAutoReconcile">Run Auto-Reconcile</span>
                        <span wire:loading wire:target="runAutoReconcile">Running...</span>
                    </button>
                @endif
            </div>
            @error('statementFile')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        @endif

        @error('selectedBankAccountId')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="fd-card overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">Unmatched / Statement Line Monitor</h3>
            <p class="text-xs text-slate-500">Track queued lines and reconciliation state from one table.</p>
        </div>
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Posted</th>
                            <th class="px-4 py-3 text-left font-semibold">Reference</th>
                            <th class="px-4 py-3 text-left font-semibold">Account</th>
                            <th class="px-4 py-3 text-left font-semibold">Direction</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold">State</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($lines as $line)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ optional($line->posted_at)->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ $line->line_reference ?: '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $line->description ?: '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $line->account?->bank_name }} | {{ $line->account?->account_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ ucfirst((string) $line->direction) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ strtoupper((string) $line->currency_code) }} {{ number_format((int) $line->amount) }}</td>
                                <td class="px-4 py-3">
                                    @if ($line->is_reconciled)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Matched</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Unmatched</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No statement lines available. Import a statement to begin reconciliation.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">Showing {{ $lines->firstItem() ?? 0 }}-{{ $lines->lastItem() ?? 0 }} of {{ $lines->total() }}</p>
                    {{ $lines->links() }}
                </div>
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Issue Queue (Inline)</h3>
                <p class="text-xs text-slate-500">Resolve/waive from this desk or open full queue for deeper triage.</p>
                <p class="mt-1 text-xs text-slate-500">Action roles: {{ implode(', ', (array) $exceptionActionAllowedRoles) }}.</p>
                @if ($makerCheckerRequired)
                    <p class="text-xs text-amber-700">Maker-checker is enabled for issue decisions.</p>
                @endif
                @if ($flowAgentsEnabled)
                    <div class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                        <span class="font-semibold">Flow Agent:</span> use <span class="font-semibold">Use Flow Agent</span> for suggested match and manual recovery guidance.
                        @if ($flowAgentsAdvisoryOnly)
                            Guidance is advisory only and does not auto-resolve issues.
                        @endif
                    </div>
                @endif
            </div>
            <a href="{{ route('treasury.reconciliation-exceptions') }}" class="inline-flex h-9 items-center rounded-lg border border-rose-300 bg-rose-50 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Open Full Issue Queue</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Issue</th>
                        <th class="px-3 py-2 text-left font-semibold">Line</th>
                        <th class="px-3 py-2 text-left font-semibold">Next Action</th>
                        <th class="px-3 py-2 text-left font-semibold">Created</th>
                        <th class="px-3 py-2 text-left font-semibold">Flow Agent</th>
                        <th class="px-3 py-2 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($openExceptionsPreview as $exception)
                        @php
                            $severityClass = match ((string) $exception->severity) {
                                'critical' => 'bg-red-100 text-red-700',
                                'high' => 'bg-rose-100 text-rose-700',
                                'medium' => 'bg-amber-100 text-amber-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-3 text-slate-600">
                                <p class="font-medium text-slate-800">{{ strtoupper((string) $exception->exception_code) }}</p>
                                <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $exception->match_stream)) }}</p>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $severityClass }}">{{ ucfirst((string) $exception->severity) }}</span>
                            </td>
                            <td class="px-3 py-3 text-slate-600">
                                <p>{{ $exception->line?->line_reference ?: '-' }}</p>
                                <p class="text-xs text-slate-500">{{ strtoupper((string) ($exception->line?->currency_code ?: 'NGN')) }} {{ number_format((int) ($exception->line?->amount ?? 0)) }}</p>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $exception->next_action ?: '-' }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ optional($exception->created_at)->format('M d, Y H:i') }}</td>
                            <td class="px-3 py-3 text-slate-600">
                                @php
                                    $insight = $flowAgentInsights[(int) $exception->id] ?? null;
                                @endphp
                                @if (is_array($insight))
                                    @php
                                        $riskClass = match ((string) ($insight['risk_level'] ?? 'low')) {
                                            'high' => 'bg-red-100 text-red-700',
                                            'medium' => 'bg-amber-100 text-amber-700',
                                            default => 'bg-emerald-100 text-emerald-700',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $riskClass }}">
                                        {{ ucfirst((string) ($insight['risk_level'] ?? 'low')) }} risk
                                    </span>
                                    <p class="mt-1 text-xs text-slate-700">{{ (string) ($insight['suggested_match'] ?? '-') }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">Confidence {{ (int) ($insight['confidence'] ?? 0) }}%</p>
                                @elseif (! $flowAgentsEnabled)
                                        <span class="text-xs text-slate-400">AI disabled for organization</span>
                                @else
                                    <span class="text-xs text-slate-400">Not analyzed</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                @if ($canResolveExceptions || $flowAgentsEnabled)
                                    <div class="inline-flex items-center gap-2">
                                        @if ($flowAgentsEnabled)
                                            <button
                                                type="button"
                                                wire:click="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 disabled:opacity-70"
                                            >
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M12 3v3"></path>
                                                    <path d="M12 18v3"></path>
                                                    <path d="M3 12h3"></path>
                                                    <path d="M18 12h3"></path>
                                                    <path d="M6.3 6.3l2.1 2.1"></path>
                                                    <path d="M15.6 15.6l2.1 2.1"></path>
                                                    <path d="M17.7 6.3l-2.1 2.1"></path>
                                                    <path d="M8.4 15.6l-2.1 2.1"></path>
                                                </svg>
                                                <span wire:loading.remove wire:target="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})">Use Flow Agent</span>
                                                <span wire:loading wire:target="analyzeOpenExceptionWithFlowAgent({{ (int) $exception->id }})">Analyzing...</span>
                                            </button>
                                        @endif
                                        @if ($canResolveExceptions)
                                            <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'resolved')" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Resolve</button>
                                            <button type="button" wire:click="openResolutionModal({{ $exception->id }}, 'waived')" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Waive</button>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">View only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">No open issues for the selected statement scope.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fd-card p-5">
        <h3 class="text-sm font-semibold text-slate-900">Close-Day Checklist</h3>
        <p class="mt-1 text-xs text-slate-500">Use this checklist before confirming daily treasury close. Backlog threshold: {{ number_format((int) $backlogAlertThreshold) }} unreconciled lines.</p>

        <div class="mt-3 space-y-2">
            @foreach ($closeDayChecklist as $item)
                <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $item['label'] }}</p>
                        <p class="text-xs text-slate-500">{{ $item['note'] }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($item['done'])
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Done</span>
                        @else
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>
                        @endif

                        @if (! $item['done'] && $item['action_route'] && $item['action_label'])
                            <a href="{{ route((string) $item['action_route']) }}" class="inline-flex rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">{{ $item['action_label'] }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($showResolutionModal)
        <div wire:click="closeResolutionModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-8">
                <div wire:click.stop class="fd-card w-full max-w-xl p-6">
                    <h3 class="text-base font-semibold text-slate-900">{{ $resolutionAction === 'waived' ? 'Waive Treasury Issue' : 'Resolve Treasury Issue' }}</h3>
                    <p class="mt-1 text-sm text-slate-600">Capture a note for audit and incident handoff clarity.</p>

                    <label class="mt-4 block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Resolution Note</span>
                        <textarea wire:model.defer="resolutionNotes" rows="4" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="What was validated and why is this closed?"></textarea>
                        @error('resolutionNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" wire:click="closeResolutionModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" wire:click="applyResolution" wire:loading.attr="disabled" wire:target="applyResolution" class="rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                            <span wire:loading.remove wire:target="applyResolution">Save</span>
                            <span wire:loading wire:target="applyResolution">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
