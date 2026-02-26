<x-app-layout :title="'Settings'" :subtitle="'Company and user configuration shell'">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('settings.company.setup') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Company Setup</h2>
            <p class="mt-2 text-sm text-slate-500">Create or update company profile and baseline configuration.</p>
        </a>

        <a href="{{ route('departments.index') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Departments</h2>
            <p class="mt-2 text-sm text-slate-500">Manage departments and assign department heads.</p>
        </a>

        <a href="{{ route('team.index') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Team</h2>
            <p class="mt-2 text-sm text-slate-500">Manage staff accounts, roles, and reporting lines.</p>
        </a>

        <a href="{{ route('approval-workflows.index') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Approval Workflows</h2>
            <p class="mt-2 text-sm text-slate-500">Configure request approval policy chains.</p>
        </a>

        <a href="{{ route('settings.communications') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Communications</h2>
            <p class="mt-2 text-sm text-slate-500">Enable channels and set organization notification fallback policy.</p>
        </a>

        <a href="{{ route('settings.request-configuration') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Request Configuration</h2>
            <p class="mt-2 text-sm text-slate-500">Manage company request types and controlled spend categories.</p>
        </a>

        <a href="{{ route('settings.expense-controls') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Expense Controls</h2>
            <p class="mt-2 text-sm text-slate-500">Configure who can post, edit, and void expenses by role and policy.</p>
        </a>

        <a href="{{ route('settings.asset-controls') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Asset Controls</h2>
            <p class="mt-2 text-sm text-slate-500">Configure who can register, assign, maintain, and dispose assets by role.</p>
        </a>

        <a href="{{ route('settings.vendor-controls') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Vendor Controls</h2>
            <p class="mt-2 text-sm text-slate-500">Configure per-action vendor permissions for profile, finance, exports, and communications.</p>
        </a>

        <a href="{{ route('profile.edit') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Profile</h2>
            <p class="mt-2 text-sm text-slate-500">Update user profile and password.</p>
        </a>
    </div>
</x-app-layout>
