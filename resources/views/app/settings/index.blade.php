<x-app-layout :title="'Settings'" :subtitle="'Company and user configuration shell'">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('settings.company.setup') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #f8fafc;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-slate-600"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21h16M6 21V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v16M9 8h2m2 0h2M9 12h2m2 0h2M10 21v-4h4v4"/></svg>
                Company Setup
            </h2>
            <p class="mt-2 text-sm text-slate-500">Create or update company profile and baseline configuration.</p>
        </a>

        <a href="{{ route('departments.index') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #f0f9ff;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-sky-700"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21h16M6 21V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v16M9 8h2m2 0h2M9 12h2m2 0h2M10 21v-4h4v4"/></svg>
                Departments
            </h2>
            <p class="mt-2 text-sm text-slate-500">Manage departments and assign department heads.</p>
        </a>

        <a href="{{ route('team.index') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #eef2ff;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-indigo-700"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1m20 0v-1a4 4 0 0 0-3-3.87M14 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm8 2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                Team
            </h2>
            <p class="mt-2 text-sm text-slate-500">Manage staff accounts, roles, and reporting lines.</p>
        </a>

        <a href="{{ route('approval-workflows.index') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #f0fdfa;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-teal-700"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h6v4H4V6Zm10 8h6v4h-6v-4ZM14 4h6v4h-6V4ZM10 8h4m0 0v8m0-8 0 0M10 16H4"/></svg>
                Approval Workflows
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure request approval policy chains.</p>
        </a>

        <a href="{{ route('settings.communications') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #ecfeff;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-cyan-700"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a8 8 0 0 1-8 8H7l-4 2 1.5-4.5A8 8 0 1 1 21 12Z"/></svg>
                Communications
            </h2>
            <p class="mt-2 text-sm text-slate-500">Enable channels and set organization notification fallback policy.</p>
        </a>

        <a href="{{ route('settings.request-configuration') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #fffbeb;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-amber-700"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6a2 2 0 0 1 2 2v1h2a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a1 1 0 0 1 1-1h2V6a2 2 0 0 1 2-2Z"/></svg>
                Request Settings
            </h2>
            <p class="mt-2 text-sm text-slate-500">Manage company request types and controlled spend categories.</p>
        </a>

        <a href="{{ route('settings.approval-timing-controls') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #f1f5f9;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-slate-700"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v6l4 2"/></svg>
                Approval Deadline Controls
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure approval response deadlines, reminders, and overdue handling for company and departments.</p>
        </a>

        <a href="{{ route('settings.expense-controls') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #eff6ff;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-blue-700"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 4.2 2.7 8 7 9 4.3-1 7-4.8 7-9V6l-7-3Z"/></svg>
                Expense Controls
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure who can post, edit, and void expenses by role and policy.</p>
        </a>

        <a href="{{ route('settings.asset-controls') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #ecfdf5;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-emerald-700"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 4.2 2.7 8 7 9 4.3-1 7-4.8 7-9V6l-7-3Z"/></svg>
                Asset Controls
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure who can register, assign, maintain, and dispose assets by role.</p>
        </a>

        <a href="{{ route('settings.vendor-controls') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #fafaf9;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-stone-700"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v6c0 4.2 2.7 8 7 9 4.3-1 7-4.8 7-9V6l-7-3Z"/></svg>
                Vendor Controls
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure per-action vendor permissions for profile, finance, exports, and communications.</p>
        </a>

        <a href="{{ route('settings.procurement-controls') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #fff7ed;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-orange-700"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M6 7V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v2m-1 4H7m12 0v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8h14Z"/></svg>
                Purchase Order Controls
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure request-to-purchase-order conversion and commitment issue controls.</p>
        </a>

        <a href="{{ route('settings.treasury-controls') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #f5f3ff;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-violet-700"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM8 10h8m-8 4h5"/></svg>
                Treasury Controls
            </h2>
            <p class="mt-2 text-sm text-slate-500">Configure statement import and reconciliation matching tolerances.</p>
        </a>
<a href="{{ route('profile.edit') }}" class="fd-card block p-6 transition hover:border-slate-300" style="background-color: #fafafa;">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-zinc-700"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0"/></svg>
                Profile
            </h2>
            <p class="mt-2 text-sm text-slate-500">Update user profile and password.</p>
        </a>
    </div>
</x-app-layout>



