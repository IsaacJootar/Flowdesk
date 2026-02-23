<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="comm-feedback-success-{{ $feedbackKey }}"
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
                wire:key="comm-feedback-error-{{ $feedbackKey }}"
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

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Communication Policy
            </span>
            <h2 class="mt-2 text-base font-semibold text-slate-900">Organization Channels</h2>
            <p class="mt-1 text-sm text-slate-600">Choose which channels are enabled and available for approval notifications.</p>
        </div>

        <form wire:submit.prevent="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="14" rx="2"></rect>
                                <path d="M8 21h8"></path>
                            </svg>
                            <span>In-app</span>
                        </p>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Configured</span>
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model.defer="in_app_enabled" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                        Enable channel
                    </label>
                    @error('in_app_enabled')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="M3 7l9 6 9-6"></path>
                            </svg>
                            <span>Email</span>
                        </p>
                        @if ($email_configured)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Configured</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Not configured</span>
                        @endif
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model.defer="email_enabled" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                        Enable channel
                    </label>
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" wire:model.defer="email_configured" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                        Mark email provider configured
                    </label>
                    @error('email_enabled')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M4 6h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"></path>
                            </svg>
                            <span>SMS</span>
                        </p>
                        @if ($sms_configured)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Configured</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Not configured</span>
                        @endif
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model.defer="sms_enabled" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                        Enable channel
                    </label>
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" wire:model.defer="sms_configured" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                        Mark SMS provider configured
                    </label>
                    @error('sms_enabled')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-semibold text-slate-900">Fallback Order</p>
                <p class="mt-1 text-xs text-slate-500">The system tries channels in this order when a selected channel fails.</p>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Primary</span>
                        <select wire:model.defer="fallback_primary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($channels as $channel)
                                <option value="{{ $channel }}">{{ strtoupper(str_replace('_', ' ', $channel)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Secondary</span>
                        <select wire:model.defer="fallback_secondary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($channels as $channel)
                                <option value="{{ $channel }}">{{ strtoupper(str_replace('_', ' ', $channel)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tertiary</span>
                        <select wire:model.defer="fallback_tertiary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($channels as $channel)
                                <option value="{{ $channel }}">{{ strtoupper(str_replace('_', ' ', $channel)) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @error('fallback_primary')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="save">Save Communication Settings</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
