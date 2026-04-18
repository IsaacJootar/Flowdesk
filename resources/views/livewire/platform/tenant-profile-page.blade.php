<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Organization Profile</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $company->name }}</h2>
            <p class="text-sm text-slate-500">{{ $company->slug }} - {{ $company->email ?: 'no email' }}</p>
        </div>
</div>

    @include('livewire.platform.partials.tenant-section-tabs', ['company' => $company, 'tenantContextRoute' => 'platform.tenants.show'])

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Lifecycle</p>
            <p class="mt-2 text-2xl font-semibold text-sky-900">{{ ucfirst((string) ($company->lifecycle_status ?: 'active')) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Plan</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ ucfirst((string) ($company->subscription?->plan_code ?? 'pilot')) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Billing State</p>
            <p class="mt-2 text-2xl font-semibold text-amber-900">{{ ucfirst((string) ($company->subscription?->subscription_status ?? 'current')) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">Users</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-900">{{ number_format((int) $company->users()->count()) }}</p>
        </div>
    </section>

    <div class="fd-card p-5">
        <h3 class="text-base font-semibold text-slate-900">Organization Identity</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Name</p>
                <p class="text-sm text-slate-800">{{ $company->name }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Slug</p>
                <p class="text-sm text-slate-800">{{ $company->slug }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Email</p>
                <p class="text-sm text-slate-800">{{ $company->email ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Phone</p>
                <p class="text-sm text-slate-800">{{ $company->phone ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Industry</p>
                <p class="text-sm text-slate-800">{{ $company->industry ?: '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Currency / Timezone</p>
                <p class="text-sm text-slate-800">{{ strtoupper((string) ($company->currency_code ?: 'NGN')) }} / {{ $company->timezone ?: 'Africa/Lagos' }}</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Address</p>
            <p class="text-sm text-slate-800">{{ $company->address ?: '-' }}</p>
        </div>

        @if ($ownerUser)
            <div class="mt-5 border-t border-slate-100 pt-5">
                <h4 class="text-sm font-semibold text-slate-900">Owner Login</h4>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Login Email</p>
                        <p class="text-sm text-slate-800">{{ $ownerUser->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Initial Password</p>
                        @if ($ownerUser->provisional_password)
                            <p class="font-mono text-sm font-semibold text-slate-800">{{ $ownerUser->provisional_password }}</p>
                            <p class="mt-0.5 text-xs text-amber-600">Not yet changed by the owner — share this securely.</p>
                        @else
                            <p class="text-sm text-slate-500">No initial password on record.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Users</h3>
                <p class="text-xs text-slate-500">Log in as any user to fix issues directly inside their account.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Email</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Access</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($allUsers as $user)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 font-medium text-slate-800">{{ $user->name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $user->email }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ ucfirst((string) $user->role) }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $user->is_active,
                                    'bg-slate-100 text-slate-500' => ! $user->is_active,
                                ])>{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="{{ route('platform.impersonate', ['userId' => $user->id]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                                        Log in as this user
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">No users found for this organization.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>




