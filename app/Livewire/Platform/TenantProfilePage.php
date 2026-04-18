<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\Company;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Client Profile')]
class TenantProfilePage extends Component
{
    use InteractsWithTenantCompanies;

    public Company $company;

    public function mount(Company $company): void
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($company);
        $this->company = $company;
        session(['platform_active_tenant_id' => (int) $company->id]);
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($this->company);

        $company = $this->tenantCompaniesBaseQuery()
            ->with(['subscription', 'featureEntitlements'])
            ->findOrFail((int) $this->company->id);

        $ownerUser = $company->users()
            ->where('role', 'owner')
            ->orderBy('id')
            ->first(['id', 'name', 'email', 'role', 'provisional_password', 'created_at']);

        $allUsers = $company->users()
            ->orderByRaw("FIELD(role, 'owner', 'admin', 'manager', 'staff', 'viewer')")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active']);

        return view('livewire.platform.tenant-profile-page', [
            'company' => $company,
            'ownerUser' => $ownerUser,
            'allUsers' => $allUsers,
        ]);
    }
}
