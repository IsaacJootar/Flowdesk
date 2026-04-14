<?php

namespace App\Livewire\Vendors;

use App\Domains\Vendors\Models\Vendor;
use App\Models\User;
use App\Services\TenantModuleAccessService;
use App\Services\Vendors\VendorCommandCenterService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Vendor Directory')]
class VendorCommandCenterPage extends Component
{
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

    public function render(TenantModuleAccessService $moduleAccessService, VendorCommandCenterService $deskService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $vendorsEnabled = $moduleAccessService->moduleEnabled($user, 'vendors');
        $procurementEnabled = $moduleAccessService->moduleEnabled($user, 'procurement');
        $requestsEnabled = $moduleAccessService->moduleEnabled($user, 'requests');

        $desk = $this->readyToLoad
            ? ($vendorsEnabled
                ? $deskService->buildDeskData($user, (bool) $procurementEnabled, (bool) $requestsEnabled, $this->search)
                : $this->emptyDeskData('Vendors module is disabled for this tenant plan.'))
            : $this->emptyDeskData('Loading vendor command center...');

        return view('livewire.vendors.vendor-command-center-page', [
            'desk' => $desk,
            'canOpenPayablesDesk' => $requestsEnabled,
            'canOpenPayoutQueue' => $requestsEnabled,
        ]);
    }

    /**
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,array<int,array<string,mixed>>>}
     */
    private function emptyDeskData(string $reason): array
    {
        return [
            'enabled' => false,
            'disabled_reason' => $reason,
            'summary' => [
                'total_vendors' => 0,
                'active_vendors' => 0,
                'open_invoices' => 0,
                'part_paid' => 0,
                'overdue' => 0,
                'blocked_handoff' => 0,
                'failed_retries' => 0,
                'workload_total' => 0,
                'bottleneck_label' => 'No blockers',
                'bottleneck_count' => 0,
                'segments' => [],
            ],
            'lanes' => [
                'profile_hygiene' => [],
                'invoice_follow_up' => [],
                'blocked_handoff' => [],
                'failed_retries' => [],
            ],
        ];
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', Vendor::class);
    }
}
