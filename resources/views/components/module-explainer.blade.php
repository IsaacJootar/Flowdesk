{{--
    Module Explainer Banner
    ──────────────────────
    A dismissible, plain-language intro banner shown at the top of each module page.
    Once dismissed it stays hidden via localStorage — reappears only on new devices.

    Usage:
        <x-module-explainer
            key="requests"
            title="Spend Requests"
            description="This is where your team submits requests for company funds — travel, vendor payments, reimbursements, and more."
            :bullets="[
                'Requests route automatically to the right approver based on your approval rules.',
                'Each request is tracked from submission through approval to payment.',
                'You can set spending limits, require attachments, and flag urgent items.',
            ]"
        />

    Props:
        key         Unique string for localStorage — use the module slug (e.g. "treasury", "requests")
        title       Plain-language module name shown in the banner heading
        description One-sentence summary of what this module does
        bullets     Optional array of plain-language "what you can do here" points
        guide_route Optional named route — renders a "Full guide →" link
--}}

@props([
    'key'         => 'module',
    'title'       => '',
    'description' => '',
    'bullets'     => [],
    'guideRoute'  => null,
])

<div
    x-data="{
        show: true,
        storageKey: 'fd_explainer_dismissed_{{ $key }}',
        init() {
            this.show = localStorage.getItem(this.storageKey) !== '1';
        },
        dismiss() {
            this.show = false;
            localStorage.setItem(this.storageKey, '1');
        },
        reset() {
            localStorage.removeItem(this.storageKey);
            this.show = true;
        }
    }"
    class="relative"
>
    {{-- Main banner --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">About this module</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $title }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>

                @if (count($bullets) > 0)
                    <ul class="mt-3 space-y-1">
                        @foreach ($bullets as $bullet)
                            <li class="flex items-start gap-2 text-sm text-slate-600">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                {{ $bullet }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($guideRoute)
                    <a href="{{ route($guideRoute) }}" class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline">
                        Full usage guide
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endif
            </div>

            <button
                type="button"
                x-on:click="dismiss"
                class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600"
                title="Dismiss"
                aria-label="Dismiss module explainer"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    {{-- Restore link shown after dismiss --}}
    <div x-show="!show" class="flex justify-end">
        <button
            type="button"
            x-on:click="reset"
            class="text-[11px] text-slate-400 underline-offset-2 hover:text-slate-600 hover:underline"
        >
            What is this module?
        </button>
    </div>
</div>
