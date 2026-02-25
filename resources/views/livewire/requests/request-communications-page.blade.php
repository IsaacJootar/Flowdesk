<div wire:init="loadData" class="space-y-5">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="request-communications-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif

        @if ($feedbackError)
            <div
                wire:key="request-communications-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-700 bg-red-600 px-4 py-3 text-sm text-white shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-100 p-1 text-xs font-semibold text-slate-700">
                <button
                    type="button"
                    wire:click="switchTab('inbox')"
                    class="rounded-lg px-3 py-1.5 transition {{ $activeTab === 'inbox' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}"
                >
                    My In-App Inbox
                    @if ($inboxUnreadCount > 0)
                        <span class="ml-1 inline-flex rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-700">{{ $inboxUnreadCount }}</span>
                    @endif
                </button>
                @if ($canViewDeliveryLogs)
                    <button
                        type="button"
                        wire:click="switchTab('delivery')"
                        class="rounded-lg px-3 py-1.5 transition {{ $activeTab === 'delivery' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-800' }}"
                    >
                        Delivery Logs
                    </button>
                @endif
            </div>

            @if ($activeTab === 'inbox')
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="markAllRead"
                        wire:loading.attr="disabled"
                        wire:target="markAllRead"
                        class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="markAllRead">Mark inbox as read</span>
                        <span wire:loading wire:target="markAllRead">Processing...</span>
                    </button>
                </div>
            @elseif ($activeTab === 'delivery' && $canManageDeliveryOps)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-[11px] font-semibold text-red-700">
                        Failed: {{ $deliverySummary['failed'] }}
                    </span>
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-700">
                        Stuck queued: {{ $deliverySummary['queued'] }}
                    </span>
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                        <span>Older than</span>
                        <input
                            type="number"
                            min="0"
                            wire:model.live.debounce.400ms="queuedOlderThanMinutes"
                            class="w-16 rounded-lg border-slate-300 px-2 py-1 text-xs focus:border-slate-500 focus:ring-slate-500"
                        >
                        <span>min</span>
                    </label>
                    <button
                        type="button"
                        wire:click="retryFailed"
                        wire:loading.attr="disabled"
                        wire:target="retryFailed"
                        class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="retryFailed">Retry Failed</span>
                        <span wire:loading wire:target="retryFailed">Retrying...</span>
                    </button>
                    <button
                        type="button"
                        wire:click="processQueuedBacklog"
                        wire:loading.attr="disabled"
                        wire:target="processQueuedBacklog"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="processQueuedBacklog">Process Queued</span>
                        <span wire:loading wire:target="processQueuedBacklog">Processing...</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-4">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Event, section, request code, vendor, invoice, recipient, message"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Channel</span>
                <select wire:model.live="channelFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All channels</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel }}">{{ strtoupper(str_replace('_', ' ', $channel)) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,channelFilter,statusFilter,activeTab,perPage,queuedOlderThanMinutes,gotoPage,previousPage,nextPage,markReadBySource,markAllRead,retryFailed,processQueuedBacklog,retryLog" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading communication records...
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Event</th>
                            <th class="px-4 py-3 text-left font-semibold">Section</th>
                            <th class="px-4 py-3 text-left font-semibold">Context</th>
                            <th class="px-4 py-3 text-left font-semibold">Channel</th>
                            <th class="px-4 py-3 text-left font-semibold">Recipient</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Time</th>
                            @if ($activeTab === 'inbox')
                                <th class="px-4 py-3 text-right font-semibold">Action</th>
                            @elseif ($activeTab === 'delivery' && $canManageDeliveryOps)
                                <th class="px-4 py-3 text-right font-semibold">Retry</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($communications as $log)
                            @php
                                $statusClass = 'bg-slate-100 text-slate-700';
                                if ($log->status === 'sent') {
                                    $statusClass = 'bg-emerald-100 text-emerald-700';
                                } elseif ($log->status === 'failed') {
                                    $statusClass = 'bg-red-100 text-red-700';
                                } elseif ($log->status === 'queued') {
                                    $statusClass = 'bg-amber-100 text-amber-700';
                                } elseif ($log->status === 'skipped') {
                                    $statusClass = 'bg-indigo-100 text-indigo-700';
                                }

                                $channelClass = 'bg-slate-100 text-slate-700';
                                if ($log->channel === 'email') {
                                    $channelClass = 'bg-blue-100 text-blue-700';
                                } elseif ($log->channel === 'sms') {
                                    $channelClass = 'bg-fuchsia-100 text-fuchsia-700';
                                } elseif ($log->channel === 'in_app') {
                                    $channelClass = 'bg-emerald-100 text-emerald-700';
                                }
                            @endphp
                            <tr wire:key="request-comm-{{ $log->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ ucwords(str_replace(['.', '_'], ' ', (string) $log->event)) }}</p>
                                    @if ($log->message)
                                        <p class="text-xs text-slate-500">{{ $log->message }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @php
                                        $sourceSection = $activeTab === 'inbox'
                                            ? (string) ($log->source_section ?? 'requests')
                                            : 'requests';
                                        $sourceClass = $sourceSection === 'vendors'
                                            ? 'bg-indigo-100 text-indigo-700'
                                            : 'bg-slate-100 text-slate-700';
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $sourceClass }}">
                                        {{ $sourceSection === 'vendors' ? 'Vendors' : 'Requests' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if ($activeTab === 'inbox' && ($log->source_section ?? 'requests') === 'vendors')
                                        <p class="font-medium text-slate-800">{{ $log->vendor_name ?? '-' }}</p>
                                        <p class="text-xs text-slate-500">Invoice: {{ $log->invoice_number ?? '-' }}</p>
                                    @else
                                        <p class="font-medium text-slate-800">{{ $activeTab === 'inbox' ? ($log->request_code ?? '-') : ($log->request?->request_code ?? '-') }}</p>
                                        <p class="text-xs text-slate-500">{{ $activeTab === 'inbox' ? ($log->request_title ?? '-') : ($log->request?->title ?? '-') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $channelClass }}">
                                        {{ strtoupper(str_replace('_', ' ', (string) $log->channel)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $activeTab === 'inbox' ? ($log->recipient_name ?? 'Assigned user') : ($log->recipient?->name ?? 'Workflow audience') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $log->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p>{{ optional($log->created_at)->format('M d, Y H:i') }}</p>
                                    @if ($activeTab === 'inbox')
                                        <p class="text-xs {{ $log->read_at ? 'text-slate-400' : 'font-semibold text-amber-700' }}">
                                            {{ $log->read_at ? 'Read' : 'Unread' }}
                                        </p>
                                    @endif
                                </td>
                                @if ($activeTab === 'inbox')
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            wire:click="markReadBySource('{{ $sourceSection }}', {{ $log->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="markReadBySource('{{ $sourceSection }}', {{ $log->id }})"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                        >
                                            Mark read
                                        </button>
                                    </td>
                                @elseif ($activeTab === 'delivery' && $canManageDeliveryOps)
                                    <td class="px-4 py-3 text-right">
                                        @if (in_array((string) $log->status, ['failed', 'queued', 'skipped'], true))
                                            <button
                                                type="button"
                                                wire:click="retryLog({{ $log->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="retryLog({{ $log->id }})"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="retryLog({{ $log->id }})">Retry now</span>
                                                <span wire:loading wire:target="retryLog({{ $log->id }})">Retrying...</span>
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $activeTab === 'inbox' ? 8 : (($activeTab === 'delivery' && $canManageDeliveryOps) ? 8 : 7) }}" class="px-4 py-10 text-center text-sm text-slate-500">
                                    @if ($activeTab === 'inbox')
                                        No in-app notifications match your filters.
                                    @else
                                        No communication delivery logs match your filters.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                        <span>Rows</span>
                        <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>

                    <div>{{ $communications->links() }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
