<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Flowdesk') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-slate-50 text-slate-900 antialiased" x-data="{ sidebarOpen: false }">
        @php
            $user = auth()->user();
            $companyName = $user?->company?->name ?? 'Flowdesk';
        @endphp

        <div class="min-h-screen md:flex">
            <aside class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-200 bg-white transition md:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
                <div class="flex h-16 items-center border-b border-slate-200 px-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Flowdesk</p>
                        <p class="text-sm font-semibold text-slate-800">{{ $companyName }}</p>
                    </div>
                </div>

                <nav class="space-y-1 px-3 py-4 text-sm">
                    <a href="{{ route('dashboard') }}" class="fd-nav-item {{ request()->routeIs('dashboard*') ? 'fd-nav-item-active' : '' }}">Dashboard</a>
                    <a href="{{ route('requests.index') }}" class="fd-nav-item {{ request()->routeIs('requests.*') ? 'fd-nav-item-active' : '' }}">Requests & Approvals</a>
                    <a href="{{ route('expenses.index') }}" class="fd-nav-item {{ request()->routeIs('expenses.*') ? 'fd-nav-item-active' : '' }}">Expenses</a>
                    <a href="{{ route('vendors.index') }}" class="fd-nav-item {{ request()->routeIs('vendors.*') ? 'fd-nav-item-active' : '' }}">Vendors</a>
                    <a href="{{ route('budgets.index') }}" class="fd-nav-item {{ request()->routeIs('budgets.*') ? 'fd-nav-item-active' : '' }}">Budgets</a>
                    <a href="{{ route('assets.index') }}" class="fd-nav-item {{ request()->routeIs('assets.*') ? 'fd-nav-item-active' : '' }}">Assets</a>
                    <span class="fd-nav-item cursor-not-allowed opacity-60">Reports</span>
                    <a href="{{ route('settings.index') }}" class="fd-nav-item {{ request()->routeIs('settings.*') ? 'fd-nav-item-active' : '' }}">Settings</a>
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
                                <p class="text-xs text-slate-500">{{ $subtitle ?? 'Modern controls for company operations' }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            @auth
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

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
