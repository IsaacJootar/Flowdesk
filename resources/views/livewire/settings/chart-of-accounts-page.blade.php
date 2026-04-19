<div class="space-y-6">
    <x-module-explainer
        key="chart-of-accounts"
        title="Chart of Accounts"
        description="Connect each Flowdesk Spend Type to the account code in your accounting system."
        :bullets="[
            'Spend Type says what the money was for.',
            'Account code says where that spend should land in your books.',
            'Flowdesk will block accounting export or sync for records that are not mapped.',
        ]"
    />

    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="chart-of-accounts-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif
    </div>

    <section class="fd-card border border-slate-200 bg-slate-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Accounting Setup</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Spend Type to Account Code</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">
                    Enter the account code from QuickBooks, Sage, Xero, or your spreadsheet template. Account name is optional, but it helps finance remember what each code means.
                </p>
            </div>

            <div class="rounded-xl border {{ $allReady ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.12em]">Mapping Progress</p>
                <p class="mt-1 text-lg font-semibold">{{ $readyCount }} / {{ $totalCount }} ready</p>
                <p class="mt-1 text-xs">{{ $allReady ? 'Ready for accounting export.' : $missingCount.' Spend Type(s) still need account codes.' }}</p>
            </div>
        </div>
    </section>

    <form wire:submit.prevent="save" class="space-y-4">
        <section class="grid gap-3 lg:grid-cols-2">
            @foreach ($categories as $category)
                @php
                    $categoryKey = (string) $category['key'];
                    $accountCode = (string) ($mappings[$categoryKey]['account_code'] ?? '');
                    $updatedByName = (string) ($mappings[$categoryKey]['updated_by_name'] ?? '');
                    $updatedAtDisplay = (string) ($mappings[$categoryKey]['updated_at_display'] ?? '');
                    $ready = trim($accountCode) !== '';
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $category['label'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $category['description'] }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $ready ? 'Mapped' : 'Needs code' }}
                        </span>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Account Code</span>
                            <input
                                type="text"
                                wire:model.defer="mappings.{{ $categoryKey }}.account_code"
                                @disabled(! $canManage)
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500"
                                placeholder="Example: 5000"
                            >
                            @error("mappings.$categoryKey.account_code")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Account Name</span>
                            <input
                                type="text"
                                wire:model.defer="mappings.{{ $categoryKey }}.account_name"
                                @disabled(! $canManage)
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500"
                                placeholder="Example: Operating Expenses"
                            >
                            @error("mappings.$categoryKey.account_name")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                        @if ($updatedAtDisplay !== '')
                            Last updated {{ $updatedAtDisplay }}{{ $updatedByName !== '' ? ' by '.$updatedByName : '' }}.
                        @else
                            Not mapped yet.
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs text-slate-500">
                {{ $canManage ? 'You can save partial mappings now and finish the rest later.' : 'View-only access. Ask owner or finance to update mappings.' }}
            </p>
            @if ($canManage)
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="save">Save Chart of Accounts</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            @endif
        </div>
    </form>
</div>
