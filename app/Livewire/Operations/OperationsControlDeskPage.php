<?php

namespace App\Livewire\Operations;

use App\Livewire\Operations\Concerns\BuildsOperationsDeskData;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Operations Overview')]
class OperationsControlDeskPage extends Component
{
    use BuildsOperationsDeskData;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function render(): View
    {
        return view('livewire.operations.operations-control-desk-page');
    }
}
