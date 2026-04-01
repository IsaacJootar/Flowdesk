<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Flowdesk') }}</title>
        <!-- Keep the tab icon on a dedicated SVG path and version it so
             browsers refresh the Flowdesk mark instead of a cached fallback. -->
        <link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('favicon.svg?v=20260328') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.svg?v=20260328') }}">
        <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico?v=20260328') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    @php
        $authUserForShell = \Illuminate\Support\Facades\Auth::user();
        $initialCompanyName = $authUserForShell?->company?->name;

        // Resolve name by company_id in case relation is not hydrated on this request.
        if (! $initialCompanyName && $authUserForShell?->company_id) {
            $initialCompanyName = \App\Domains\Company\Models\Company::query()
                ->whereKey((int) $authUserForShell->company_id)
                ->value('name');
        }

        if (! $initialCompanyName) {
            $initialCompanyName = app(\App\Services\PlatformAccessService::class)->isPlatformOperator($authUserForShell)
                ? 'Platform Control'
                : 'Organization';
        }
    @endphp
    <body
        class="bg-slate-50 text-slate-900 antialiased"
        x-data="{ sidebarOpen: false, companyName: @js($initialCompanyName) }"
        x-on:company-name-updated.window="companyName = ($event.detail && $event.detail.name) ? $event.detail.name : companyName"
    >
        @php
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user) {
                $user->loadMissing([
                    'department:id,name',
                    'reportsTo:id,name',
                ]);
            }
            $platformAccess = app(\App\Services\PlatformAccessService::class);
            $isPlatformOperator = $platformAccess->isPlatformOperator($user);
            $companyName = (string) $initialCompanyName;
            $role = $user?->role ?? 'staff';
            $roleLabel = $isPlatformOperator
                ? 'Flowdesk Platform Control Center'
                : match ((string) $role) {
                    'owner' => 'Admin (Owner)',
                    'finance' => 'Finance',
                    'manager' => 'Manager',
                    'auditor' => 'Auditor',
                    default => 'Staff',
                };
            $departmentLabel = $isPlatformOperator ? 'Platform Control' : ($user?->department?->name ?? 'No department');
            $reportsToLabel = $isPlatformOperator ? 'N/A' : ($user?->reportsTo?->name ?? 'Not assigned');
            $showReportsToSection = ! $isPlatformOperator
                && (string) $role !== 'owner'
                && filled($user?->reportsTo?->name);
            $navigation = app(\App\Services\NavAccessService::class)->forUser($user);
            $navItems = $navigation['items'];
            $showBackToSettings = request()->routeIs('settings.organization') || request()->routeIs('settings.company.setup');
        @endphp

        <div class="min-h-screen md:flex">
            <div
                x-cloak
                x-show="sidebarOpen"
                x-transition.opacity.duration.200ms
                class="fixed inset-0 z-30 bg-slate-900/40 md:hidden"
                @click="sidebarOpen = false"
                aria-hidden="true"
            ></div>

            <aside class="fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto border-r border-slate-200 bg-white transition md:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
                <div class="flex h-24 items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <img src="{{ asset('brand-logo.svg') }}" alt="Flowdesk" class="h-10 w-auto">
                        <p class="mt-2 max-w-[13rem] truncate text-xs font-medium text-slate-600" x-text="companyName">{{ $companyName }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 md:hidden"
                        @click="sidebarOpen = false"
                        aria-label="Close menu"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>

                <nav class="space-y-1 px-3 py-4 pb-8 text-sm">
                    @foreach ($navItems as $item)
                        @php
                            $patterns = (array) ($item['pattern'] ?? []);
                            $icon = (string) ($item['icon'] ?? 'dot');
                            $params = (array) ($item['params'] ?? []);
                        @endphp
                        <a
                            href="{{ route($item['route'], $params) }}"
                            class="fd-nav-item {{ request()->routeIs(...$patterns) ? 'fd-nav-item-active' : '' }}"
                        >
                            <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-slate-500">
                                @switch($icon)
                                    @case('home')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.75 12 4l9 6.75V20a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-9.25Z"/></svg>
                                        @break
                                    @case('chart')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h16M7 16V9m5 7V5m5 11v-4"/></svg>
                                        @break
                                    @case('clipboard')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6a2 2 0 0 1 2 2v1h2a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a1 1 0 0 1 1-1h2V6a2 2 0 0 1 2-2Z"/></svg>
                                        @break
                                    @case('inbox')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6M3 13l2.2 4.4a1 1 0 0 0 .9.6h11.8a1 1 0 0 0 .9-.6L21 13m-18 0h5l1.5 2h5L16 13h5"/></svg>
                                        @break
                                    @case('receipt')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h4"/></svg>
                                        @break
                                    @case('building')
                                    @case('office')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21h16M6 21V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v16M9 8h2m2 0h2M9 12h2m2 0h2M10 21v-4h4v4"/></svg>
                                        @break
                                    @case('wallet')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Zm0 0 2-3h14l2 3M16 13h3"/></svg>
                                        @break
                                    @case('cube')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm0 0v18m8-13.5-8 4.5-8-4.5"/></svg>
                                        @break
                                    @case('users')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1m20 0v-1a4 4 0 0 0-3-3.87M14 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm8 2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        @break
                                    @case('flow')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h6v4H4V6Zm10 8h6v4h-6v-4ZM14 4h6v4h-6V4ZM10 8h4m0 0v8m0-8 0 0M10 16H4"/></svg>
                                        @break
                                    @case('chat')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a8 8 0 0 1-8 8H7l-4 2 1.5-4.5A8 8 0 1 1 21 12Z"/></svg>
                                        @break
                                    @case('sliders')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M8 6v12M4 12h16m-6 0v6M4 18h16"/></svg>
                                        @break
                                    @case('clock')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v6l4 2"/></svg>
                                        @break
                                    @case('shield')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 4.2 2.7 8 7 9 4.3-1 7-4.8 7-9V6l-7-3Z"/></svg>
                                        @break
                                    @case('cog')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Zm7.4-3.5a7.3 7.3 0 0 0-.1-1l2-1.6-2-3.4-2.5 1a7.8 7.8 0 0 0-1.7-1l-.4-2.6H9.3L9 6a7.8 7.8 0 0 0-1.7 1l-2.5-1-2 3.4 2 1.6a7.3 7.3 0 0 0 0 2l-2 1.6 2 3.4 2.5-1a7.8 7.8 0 0 0 1.7 1l.4 2.6h4.4l.4-2.6a7.8 7.8 0 0 0 1.7-1l2.5 1 2-3.4-2-1.6c.1-.3.1-.7.1-1Z"/></svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-2.5 w-2.5"><circle cx="12" cy="12" r="3"/></svg>
                                @endswitch
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="flex-1 md:ml-64">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center gap-3">
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 md:hidden" @click="sidebarOpen = !sidebarOpen">
                                Menu
                            </button>
                            <div>
                                <h1 class="text-base font-semibold text-slate-900">{{ $title ?? 'Workspace' }}</h1>
                                <p class="text-xs text-slate-500">{{ $subtitle ?? config('page_subtitles.'.($title ?? ''), 'Built for modern organizations') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($showBackToSettings)
                                <a href="{{ route('settings.index') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Back to Settings</a>
                            @endif
                            @auth
                                <div class="hidden items-center gap-2 lg:flex">
                                    @if ($isPlatformOperator)
                                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                            {{ $roleLabel }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                            {{ $roleLabel }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                            {{ $departmentLabel }}
                                        </span>
                                        @if ($showReportsToSection)
                                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                                Reports to: {{ $reportsToLabel }}
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                <a href="{{ route('profile.edit') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Profile</a>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Log out</button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </header>

                <main class="p-4 sm:p-6 lg:p-8">
                    @if (session('status'))
                        <div
                            x-data="{ show: true }"
                            x-init="setTimeout(() => show = false, 3200)"
                            x-show="show"
                            x-transition.opacity.duration.250ms
                            class="pointer-events-none fixed z-[90]"
                            style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
                        >
                            <div class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg">
                                {{ session('status') }}
                            </div>
                        </div>
                    @endif

                    @isset($header)
                        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            {{ $header }}
                        </div>
                    @endisset

                    @auth
                        <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Signed In User</p>
                                    <p class="mt-1 text-sm font-semibold text-sky-900">{{ $user?->name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Role / Designation</p>
                                    <p class="mt-1 text-sm font-semibold text-sky-900">{{ $roleLabel }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Department</p>
                                    <p class="mt-1 text-sm font-semibold text-sky-900">{{ $departmentLabel }}</p>
                                </div>
                                @if ($isPlatformOperator || $showReportsToSection)
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Reporting Line</p>
                                        <p class="mt-1 text-sm font-semibold text-sky-900">
                                            {{ $isPlatformOperator ? 'Global tenant oversight' : 'Direct Manager ('.$reportsToLabel.')' }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endauth

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>









