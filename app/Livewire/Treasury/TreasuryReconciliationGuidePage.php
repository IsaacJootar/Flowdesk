<?php

namespace App\Livewire\Treasury;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Treasury Reconciliation Help')]
class TreasuryReconciliationGuidePage extends Component
{
    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function render(): View
    {
        return view('livewire.treasury.treasury-reconciliation-guide-page');
    }

    private function canAccessPage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }
}