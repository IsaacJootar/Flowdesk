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
    <body
        class="bg-slate-50 text-slate-900 antialiased"
        x-data="{ sidebarOpen: false, companyName: @js(auth()->user()?->company?->name ?? 'Flowdesk') }"
        x-on:company-name-updated.window="companyName = ($event.detail && $event.detail.name) ? $event.detail.name : companyName"
    >
        @php
            $user = auth()->user();
            if ($user) {
                $user->loadMissing([
                    'department:id,name',
                    'reportsTo:id,name',
                ]);
            }
            $companyName = $user?->company?->name ?? 'Flowdesk';
            $role = $user?->role ?? 'staff';
            $roleLabel = match ((string) $role) {
                'owner' => 'Admin (Owner)',
                'finance' => 'Finance',
                'manager' => 'Manager',
                'auditor' => 'Auditor',
                default => 'Staff',
            };
            $departmentLabel = $user?->department?->name ?? 'No department';
            $reportsToLabel = $user?->reportsTo?->name ?? 'Not assigned';

            $navByRole = [
                'owner' => [
                    ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard'],
                    ['route' => 'requests.index', 'pattern' => 'requests.index', 'label' => 'Requests & Approvals'],
                    ['route' => 'requests.communications', 'pattern' => 'requests.communications', 'label' => 'Request Inbox & Logs'],
                    ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'label' => 'Expenses'],
                    ['route' => 'vendors.index', 'pattern' => 'vendors.*', 'label' => 'Vendors'],
                    ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'label' => 'Budgets'],
                    ['route' => 'assets.index', 'pattern' => 'assets.*', 'label' => 'Assets'],
                    ['route' => 'departments.index', 'pattern' => 'departments.*', 'label' => 'Departments'],
                    ['route' => 'team.index', 'pattern' => 'team.*', 'label' => 'Team'],
                    ['route' => 'approval-workflows.index', 'pattern' => 'approval-workflows.*', 'label' => 'Approval Workflows'],
                    ['route' => 'settings.communications', 'pattern' => 'settings.communications', 'label' => 'Communications'],
                    ['route' => 'settings.request-configuration', 'pattern' => 'settings.request-configuration', 'label' => 'Request Configuration'],
                    ['route' => 'settings.index', 'pattern' => 'settings.*', 'label' => 'Settings'],
                ],
                'finance' => [
                    ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard'],
                    ['route' => 'requests.index', 'pattern' => 'requests.index', 'label' => 'Requests & Approvals'],
                    ['route' => 'requests.communications', 'pattern' => 'requests.communications', 'label' => 'Request Inbox & Logs'],
                    ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'label' => 'Expenses'],
                    ['route' => 'vendors.index', 'pattern' => 'vendors.*', 'label' => 'Vendors'],
                    ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'label' => 'Budgets'],
                    ['route' => 'assets.index', 'pattern' => 'assets.*', 'label' => 'Assets'],
                ],
                'manager' => [
                    ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard'],
                    ['route' => 'requests.index', 'pattern' => 'requests.index', 'label' => 'Requests & Approvals'],
                    ['route' => 'requests.communications', 'pattern' => 'requests.communications', 'label' => 'Request Inbox & Logs'],
                    ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'label' => 'Expenses'],
                    ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'label' => 'Budgets'],
                    ['route' => 'assets.index', 'pattern' => 'assets.*', 'label' => 'Assets'],
                ],
                'staff' => [
                    ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard'],
                    ['route' => 'requests.index', 'pattern' => 'requests.index', 'label' => 'Requests & Approvals'],
                    ['route' => 'requests.communications', 'pattern' => 'requests.communications', 'label' => 'Request Inbox & Logs'],
                    ['route' => 'assets.index', 'pattern' => 'assets.*', 'label' => 'Assets'],
                ],
                'auditor' => [
                    ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard'],
                    ['route' => 'requests.index', 'pattern' => 'requests.index', 'label' => 'Requests & Approvals'],
                    ['route' => 'requests.communications', 'pattern' => 'requests.communications', 'label' => 'Request Inbox & Logs'],
                    ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'label' => 'Expenses'],
                    ['route' => 'vendors.index', 'pattern' => 'vendors.*', 'label' => 'Vendors'],
                    ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'label' => 'Budgets'],
                    ['route' => 'assets.index', 'pattern' => 'assets.*', 'label' => 'Assets'],
                ],
            ];

            $navItems = $navByRole[$role] ?? $navByRole['staff'];
        @endphp

        <div class="min-h-screen md:flex">
            <aside class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-200 bg-white transition md:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">
                <div class="flex h-16 items-center border-b border-slate-200 px-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Flowdesk</p>
                        <p class="text-sm font-semibold text-slate-800" x-text="companyName">{{ $companyName }}</p>
                    </div>
                </div>

                <nav class="space-y-1 px-3 py-4 text-sm">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="fd-nav-item {{ request()->routeIs($item['pattern']) ? 'fd-nav-item-active' : '' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach

                    @if (in_array($role, ['owner', 'finance', 'manager', 'auditor'], true))
                        <span class="fd-nav-item cursor-not-allowed opacity-60">Reports</span>
                    @endif
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
                                <p class="text-xs text-slate-500">{{ $subtitle ?? 'Built for modern organizations' }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            @auth
                                <div class="hidden items-center gap-2 lg:flex">
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ $roleLabel }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                        {{ $departmentLabel }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                        Reports to: {{ $reportsToLabel }}
                                    </span>
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
                        @unless (request()->routeIs('dashboard*'))
                            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Signed In User</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $user?->name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Role / Designation</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $roleLabel }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Department</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $departmentLabel }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Reporting Line</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">Direct Manager ({{ $reportsToLabel }})</p>
                                    </div>
                                </div>
                            </div>
                        @endunless
                    @endauth

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
