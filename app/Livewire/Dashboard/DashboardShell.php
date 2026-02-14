<?php

namespace App\Livewire\Dashboard;

use App\Domains\Company\Models\Department;
use App\Models\User;
use Livewire\Component;

class DashboardShell extends Component
{
    public bool $readyToLoad = false;

    /** @var array<string, array{label: string, value: string, hint: string}> */
    public array $metrics = [];

    public function loadMetrics(): void
    {
        $this->readyToLoad = true;

        $companyId = auth()->user()?->company_id;

        if (! $companyId) {
            $this->metrics = $this->defaultMetrics();

            return;
        }

        $departmentCount = Department::query()->count();
        $userCount = User::query()->where('company_id', $companyId)->count();

        $this->metrics = [
            'total_spend' => [
                'label' => 'Total Spend (This Month)',
                'value' => 'NGN 0.00',
                'hint' => 'Skeleton metric',
            ],
            'pending_approvals' => [
                'label' => 'Pending Approvals',
                'value' => '0 requests',
                'hint' => 'Modules not enabled yet',
            ],
            'budget_remaining' => [
                'label' => 'Budget Remaining',
                'value' => 'NGN 0.00',
                'hint' => 'Budget module pending',
            ],
            'assets_overview' => [
                'label' => 'Assets Overview',
                'value' => '0 total / 0 assigned / 0 missing',
                'hint' => 'Asset module pending',
            ],
            'departments' => [
                'label' => 'Departments',
                'value' => (string) $departmentCount,
                'hint' => 'Company structure ready',
            ],
            'users' => [
                'label' => 'Users',
                'value' => (string) $userCount,
                'hint' => 'Identity base ready',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-shell')
            ->layout('layouts.app', [
                'title' => 'Dashboard',
                'subtitle' => 'Flowdesk control center skeleton',
            ]);
    }

    /**
     * @return array<string, array{label: string, value: string, hint: string}>
     */
    private function defaultMetrics(): array
    {
        return [
            'total_spend' => [
                'label' => 'Total Spend (This Month)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'pending_approvals' => [
                'label' => 'Pending Approvals',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'budget_remaining' => [
                'label' => 'Budget Remaining',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'assets_overview' => [
                'label' => 'Assets Overview',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
        ];
    }
}
