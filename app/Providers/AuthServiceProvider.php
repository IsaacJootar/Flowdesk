<?php

namespace App\Providers;

use App\Domains\Assets\Models\Asset;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Policies\AssetPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\RequestPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        SpendRequest::class => RequestPolicy::class,
        Expense::class => ExpensePolicy::class,
        Vendor::class => VendorPolicy::class,
        DepartmentBudget::class => BudgetPolicy::class,
        Asset::class => AssetPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
