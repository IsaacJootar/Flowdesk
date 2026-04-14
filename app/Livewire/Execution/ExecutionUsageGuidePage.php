<?php

namespace App\Livewire\Execution;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payment Provider Guide')]
class ExecutionUsageGuidePage extends Component
{
    public function mount(): void
    {
        abort_unless($this->canAccessPage(), 403);
    }

    public function render(): View
    {
        return view('livewire.execution.execution-usage-guide-page');
    }

    private function canAccessPage(): bool
    {
        return Gate::allows('viewAny', RequestPayoutExecutionAttempt::class);
    }
}
