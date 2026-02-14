<x-app-layout :title="'Settings'" :subtitle="'Company and user configuration shell'">
    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('settings.company.setup') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Company Setup</h2>
            <p class="mt-2 text-sm text-slate-500">Create or review company baseline configuration.</p>
        </a>

        <a href="{{ route('profile.edit') }}" class="fd-card block p-6 transition hover:border-slate-300">
            <h2 class="text-sm font-semibold text-slate-900">Profile</h2>
            <p class="mt-2 text-sm text-slate-500">Update user profile and password.</p>
        </a>
    </div>
</x-app-layout>
