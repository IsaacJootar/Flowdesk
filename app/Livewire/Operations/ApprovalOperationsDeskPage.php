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
#[Title('Approvals Overview')]
class ApprovalOperationsDeskPage extends Component
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

        $requestsEnabled = $moduleAccessService->moduleEnabled($user, 'requests');

        $approvalDesk = $this->readyToLoad
            ? $this->buildApprovalDesk($user, (bool) $requestsEnabled, $this->search)
            : $this->emptyDeskData('Requests module is required for approval operations.');

        return view('livewire.operations.approval-operations-desk-page', [
            'approvalDesk' => $approvalDesk,
        ]);
    }
}
