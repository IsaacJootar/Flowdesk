<?php

namespace App\Livewire\Dashboard;

use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class DashboardShell extends Component
{
    public bool $readyToLoad = false;

    /** @var array<string, array{label: string, value: string, hint: string, words?: string}> */
    public array $metrics = [];

    public function mount(): void
    {
        $this->loadMetrics();
    }

    public function loadMetrics(): void
    {
        $this->readyToLoad = true;

        $user = \Illuminate\Support\Facades\Auth::user();
        $companyId = $user?->company_id;

        if (! $companyId) {
            $this->metrics = $this->defaultMetrics();

            return;
        }

        $currencyCode = strtoupper((string) ($user?->company?->currency_code ?: 'NGN'));
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $departmentCount = Department::query()->count();
        $userCount = User::query()->where('company_id', $companyId)->count();
        $monthSpend = (int) Expense::query()
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $requestsInReviewCount = (int) SpendRequest::query()
            ->where('status', 'in_review')
            ->count();
        $requestsInReviewValue = (int) SpendRequest::query()
            ->where('status', 'in_review')
            ->sum('amount');
        $approvedThisMonthCount = (int) SpendRequest::query()
            ->where('status', 'approved')
            ->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $approvedThisMonthValue = (int) SpendRequest::query()
            ->where('status', 'approved')
            ->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('approved_amount');
        $activeBudgetBaseQuery = DepartmentBudget::query()
            ->where('status', 'active')
            ->whereDate('period_start', '<=', today())
            ->whereDate('period_end', '>=', today());
        $activeBudgetCount = (int) (clone $activeBudgetBaseQuery)->count();
        $approvedBudgetTotal = (int) (clone $activeBudgetBaseQuery)->sum('allocated_amount');
        $budgetRemainingTotal = (int) (clone $activeBudgetBaseQuery)->sum('remaining_amount');

        $this->metrics = [
            'total_spend' => [
                'label' => 'Total Spend (This Month)',
                'value' => sprintf('%s %s', $currencyCode, number_format($monthSpend, 2)),
                'hint' => sprintf('Posted expenses for %s', now()->format('F Y')),
                'words' => $this->formatAmountInWords($monthSpend, $currencyCode),
            ],
            'pending_approvals' => [
                'label' => 'Requests In Review',
                'value' => sprintf('%s requests', number_format($requestsInReviewCount)),
                'hint' => sprintf('Pipeline value: %s %s', $currencyCode, number_format($requestsInReviewValue, 2)),
                'words' => $this->formatAmountInWords($requestsInReviewValue, $currencyCode),
            ],
            'approved_value_month' => [
                'label' => 'Approved Value (This Month)',
                'value' => sprintf('%s %s', $currencyCode, number_format($approvedThisMonthValue, 2)),
                'hint' => sprintf('%s approved requests this month', number_format($approvedThisMonthCount)),
                'words' => $this->formatAmountInWords($approvedThisMonthValue, $currencyCode),
            ],
            'approved_budget' => [
                'label' => 'Approved Budget (Active)',
                'value' => sprintf('%s %s', $currencyCode, number_format($approvedBudgetTotal, 2)),
                'hint' => sprintf('%s active department budgets', number_format($activeBudgetCount)),
                'words' => $this->formatAmountInWords($approvedBudgetTotal, $currencyCode),
            ],
            'budget_remaining' => [
                'label' => 'Budget Remaining (Active)',
                'value' => sprintf('%s %s', $currencyCode, number_format($budgetRemainingTotal, 2)),
                'hint' => 'Remaining balance across active budgets',
                'words' => $this->formatAmountInWords($budgetRemainingTotal, $currencyCode),
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
            ;
    }

    /**
     * @return array<string, array{label: string, value: string, hint: string, words?: string}>
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
                'label' => 'Requests In Review',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'approved_value_month' => [
                'label' => 'Approved Value (This Month)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'approved_budget' => [
                'label' => 'Approved Budget (Active)',
                'value' => '---',
                'hint' => 'Run company setup first',
            ],
            'budget_remaining' => [
                'label' => 'Budget Remaining (Active)',
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

    private function formatAmountInWords(int $amount, string $currencyCode): string
    {
        $unit = strtoupper($currencyCode) === 'NGN' ? 'naira' : strtolower($currencyCode);
        $words = $this->numberToWords(max(0, $amount));

        return sprintf('In words: %s %s', $words, $unit);
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $units = [
            0 => '',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
        ];

        $tens = [
            2 => 'twenty',
            3 => 'thirty',
            4 => 'forty',
            5 => 'fifty',
            6 => 'sixty',
            7 => 'seventy',
            8 => 'eighty',
            9 => 'ninety',
        ];

        $scales = [
            1_000_000_000_000 => 'trillion',
            1_000_000_000 => 'billion',
            1_000_000 => 'million',
            1_000 => 'thousand',
            1 => '',
        ];

        $parts = [];

        foreach ($scales as $scaleValue => $scaleLabel) {
            if ($number < $scaleValue) {
                continue;
            }

            $chunk = intdiv($number, $scaleValue);
            $number %= $scaleValue;

            if ($chunk === 0) {
                continue;
            }

            $chunkWords = $this->chunkToWords($chunk, $units, $tens);
            $parts[] = trim($chunkWords.' '.$scaleLabel);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, string>  $units
     * @param  array<int, string>  $tens
     */
    private function chunkToWords(int $number, array $units, array $tens): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = $units[intdiv($number, 100)].' hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = $tens[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0 && $number < 20) {
            $words[] = $units[$number];
        }

        return implode(' ', $words);
    }
}

