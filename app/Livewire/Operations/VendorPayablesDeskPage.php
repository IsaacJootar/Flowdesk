<?php

namespace App\Livewire\Operations;

use App\Livewire\Operations\Concerns\BuildsOperationsDeskData;
use App\Models\User;
use App\Services\TenantModuleAccessService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Vendor Payables')]
class VendorPayablesDeskPage extends Component
{
    use BuildsOperationsDeskData;

    public bool $readyToLoad = false;

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function render(TenantModuleAccessService $moduleAccessService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $vendorsEnabled = $moduleAccessService->moduleEnabled($user, 'vendors');
        $procurementEnabled = $moduleAccessService->moduleEnabled($user, 'procurement');
        $requestsEnabled = $moduleAccessService->moduleEnabled($user, 'requests');

        $payablesDesk = $this->readyToLoad
            ? $this->buildPayablesDesk(
                user: $user,
                vendorsEnabled: (bool) $vendorsEnabled,
                procurementEnabled: (bool) $procurementEnabled,
                requestsEnabled: (bool) $requestsEnabled,
                search: $this->search,
            )
            : $this->emptyDeskData('Vendors module is required for payables operations.');

        return view('livewire.operations.vendor-payables-desk-page', [
            'payablesDesk' => $payablesDesk,
        ]);
    }
}
