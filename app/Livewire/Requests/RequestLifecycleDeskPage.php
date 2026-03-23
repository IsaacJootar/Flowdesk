<?php

namespace App\Livewire\Requests;

use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Requests\RequestLifecycleDeskService;
use App\Services\TenantModuleAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request Lifecycle Desk')]
class RequestLifecycleDeskPage extends Component
{
    public bool $readyToLoad = false;

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $deepLinkSearch = trim((string) request()->query('search', ''));
        if ($deepLinkSearch !== '') {
            $this->search = mb_substr($deepLinkSearch, 0, 120);
        }
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 120);
    }

    public function render(TenantModuleAccessService $moduleAccessService, RequestLifecycleDeskService $deskService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $requestsEnabled = $moduleAccessService->moduleEnabled($user, 'requests');

        $desk = $this->readyToLoad
            ? ($requestsEnabled
                ? $deskService->buildDeskData($user, $this->search)
                : $this->emptyDeskData('Requests module is disabled for this tenant plan.'))
            : $this->emptyDeskData('Loading request lifecycle desk...');

        return view('livewire.requests.request-lifecycle-desk-page', [
            'desk' => $desk,
            'canOpenPayoutQueue' => $this->canOpenPayoutQueue($user),
        ]);
    }

    private function canOpenPayoutQueue(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', SpendRequest::class);
    }

    /**
     * @return array{
     *   enabled: bool,
     *   disabled_reason: ?string,
     *   summary: array<string, mixed>,
     *   lanes: array<string, array<int, array<string, mixed>>>
     * }
     */
    private function emptyDeskData(string $reason): array
    {
        return [
            'enabled' => false,
            'disabled_reason' => $reason,
            'summary' => [
                'approved_need_po' => 0,
                'po_match_followup' => 0,
                'waiting_dispatch' => 0,
                'execution_active_retry' => 0,
                'closed_outcomes' => 0,
                'workload_total' => 0,
                'bottleneck_label' => 'No blockers',
                'bottleneck_count' => 0,
                'segments' => [],
            ],
            'lanes' => [
                'approved_need_po' => [],
                'po_match_followup' => [],
                'waiting_dispatch' => [],
                'execution_active_retry' => [],
                'closed_outcomes' => [],
            ],
        ];
    }
}
