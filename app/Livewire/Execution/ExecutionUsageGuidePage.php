<?php

namespace App\Livewire\Execution;

use App\Enums\UserRole;
use App\Services\PlatformAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Execution Help')]
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
        $user = Auth::user();
        if (! $user || app(PlatformAccessService::class)->isPlatformOperator($user)) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }
}