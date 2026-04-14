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
#[Title('Month-End Close')]
class PeriodCloseDeskPage extends Component
{
    use BuildsOperationsDeskData;

    public bool $readyToLoad = false;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function render(TenantModuleAccessService $moduleAccessService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $requestsEnabled = $moduleAccessService->moduleEnabled($user, 'requests');
        $procurementEnabled = $moduleAccessService->moduleEnabled($user, 'procurement');
        $treasuryEnabled = $moduleAccessService->moduleEnabled($user, 'treasury');

        $closeDesk = $this->readyToLoad
            ? $this->buildCloseDesk(
                user: $user,
                requestsEnabled: (bool) $requestsEnabled,
                procurementEnabled: (bool) $procurementEnabled,
                treasuryEnabled: (bool) $treasuryEnabled,
            )
            : $this->emptyDeskData('Close readiness requires treasury, procurement, and execution data.');

        return view('livewire.operations.period-close-desk-page', [
            'closeDesk' => $closeDesk,
        ]);
    }
}
