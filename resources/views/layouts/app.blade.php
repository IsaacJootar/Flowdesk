<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Flowdesk') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('brand-mark.svg') }}">
        <link rel="shortcut icon" href="{{ asset('brand-mark.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body
        class="bg-slate-50 text-slate-900 antialiased"
        x-data="{ sidebarOpen: false, companyName: @js(\Illuminate\Support\Facades\Auth::user()?->company?->name ?? 'Flowdesk') }"
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
            $companyName = $user?->company?->name ?? 'Flowdesk';
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
            $navigation = app(\App\Services\NavAccessService::class)->forUser($user);
            $navItems = $navigation['items'];
        @endphp

        <div class="min-h-screen md:flex">
            <aside class="fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto border-r border-slate-200 bg-white transition md:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
                <div class="flex h-16 items-center border-b border-slate-200 px-5">
                    <div>
                        <img src="{{ asset('brand-logo.svg') }}" alt="Flowdesk" class="mt-1 h-6 w-auto">
                        @if (! $isPlatformOperator)
                            <p class="text-sm font-semibold text-slate-800" x-text="companyName">{{ $companyName }}</p>
                        @endif
                    </div>
                </div>

                <nav class="space-y-1 px-3 py-4 pb-8 text-sm">
                    @foreach ($navItems as $item)
                        @php
                            $patterns = (array) ($item['pattern'] ?? []);
                        @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="fd-nav-item {{ request()->routeIs(...$patterns) ? 'fd-nav-item-active' : '' }}"
                        >
                            {{ $item['label'] }}
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
                                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                            Reports to: {{ $reportsToLabel }}
                                        </span>
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
                                <div>
                                    <p class="text-xs uppercase tracking-[0.1em] text-sky-700">Reporting Line</p>
                                    <p class="mt-1 text-sm font-semibold text-sky-900">
                                        {{ $isPlatformOperator ? 'Global tenant oversight' : 'Direct Manager ('.$reportsToLabel.')' }}
                                    </p>
                                </div>
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
