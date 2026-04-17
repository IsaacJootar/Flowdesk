<?php

namespace App\Livewire\Reports;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Budget to Payment Guide')]
class FinancialTraceGuidePage extends Component
{
    public function mount(): void
    {
        abort_unless($this->canAccessGuide(), 403);
    }

    public function render(): View
    {
        return view('livewire.reports.financial-trace-guide-page');
    }

    private function canAccessGuide(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->is_active) {
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
