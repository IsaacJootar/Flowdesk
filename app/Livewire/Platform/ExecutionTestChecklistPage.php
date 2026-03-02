<?php

namespace App\Livewire\Platform;

use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Execution Test Checklist')]
class ExecutionTestChecklistPage extends Component
{
    use InteractsWithTenantCompanies;

    public function render(): View
    {
        $this->authorizePlatformOperator();

        $latestTenant = $this->tenantCompaniesBaseQuery()
            ->latest('created_at')
            ->latest('id')
            ->first();

        return view('livewire.platform.execution-test-checklist-page', [
            'latestTenant' => $latestTenant,
        ]);
    }
}
