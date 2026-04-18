<div class="relative" x-data="{ open: @entangle('open') }" @click.outside="open = false">
    <button
        type="button"
        wire:click="toggle"
        class="relative rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 hover:text-slate-700"
        aria-label="Notifications"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14V11a6 6 0 0 0-5-5.9V4a1 1 0 1 0-2 0v1.1A6 6 0 0 0 6 11v3a2 2 0 0 1-.6 1.6L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold leading-none text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-full z-50 mt-2 w-80 origin-top-right rounded-xl border border-slate-200 bg-white shadow-lg"
        style="display: none;"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <span class="text-sm font-semibold text-slate-800">Notifications</span>
            @if ($unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllRead"
                    class="text-xs font-medium text-sky-600 hover:text-sky-700"
                >
                    Mark all read
                </button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                @php
                    $label = match(true) {
                        str_contains($notification['event'], 'submitted') => 'Request submitted',
                        str_contains($notification['event'], 'approved') => 'Request approved',
                        str_contains($notification['event'], 'rejected') => 'Request rejected',
                        str_contains($notification['event'], 'pending') => 'Awaiting your approval',
                        str_contains($notification['event'], 'cancelled') => 'Request cancelled',
                        str_contains($notification['event'], 'paid') => 'Payment processed',
                        str_contains($notification['event'], 'comment') => 'New comment',
                        default => ucwords(str_replace(['.', '_', '-'], ' ', $notification['event'])),
                    };
                @endphp
                <a
                    href="{{ $notification['request_id'] ? route('requests.index') : route('requests.communications') }}"
                    class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 {{ $notification['read'] ? '' : 'bg-amber-50/60' }}"
                >
                    <span class="mt-0.5 flex h-2 w-2 shrink-0 items-center justify-center">
                        @if (! $notification['read'])
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        @else
                            <span class="h-2 w-2 rounded-full bg-slate-200"></span>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-slate-800">{{ $label }}</p>
                        @if ($notification['request_code'])
                            <p class="text-xs text-slate-500">{{ $notification['request_code'] }}</p>
                        @endif
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ $notification['created_at']?->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center">
                    <p class="text-xs text-slate-400">No notifications yet</p>
                </div>
            @endforelse
        </div>

        <div class="border-t border-slate-100 px-4 py-2.5">
            <a href="{{ route('requests.communications') }}" class="block text-center text-xs font-medium text-sky-600 hover:text-sky-700">
                View all in Inbox & Logs
            </a>
        </div>
    </div>
</div>
