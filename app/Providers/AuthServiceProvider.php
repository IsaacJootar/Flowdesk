<?php

namespace App\Providers;

use App\Domains\Assets\Models\Asset;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\PaymentRun;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Vendors\Models\Vendor;
use App\Policies\AssetPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\BankStatementPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\InvoiceMatchExceptionPolicy;
use App\Policies\PaymentRunPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReconciliationExceptionPolicy;
use App\Policies\RequestPolicy;
use App\Policies\RequestPayoutExecutionAttemptPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        SpendRequest::class => RequestPolicy::class,
        RequestPayoutExecutionAttempt::class => RequestPayoutExecutionAttemptPolicy::class,
        Expense::class => ExpensePolicy::class,
        Vendor::class => VendorPolicy::class,
        DepartmentBudget::class => BudgetPolicy::class,
        Asset::class => AssetPolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        GoodsReceipt::class => GoodsReceiptPolicy::class,
        InvoiceMatchException::class => InvoiceMatchExceptionPolicy::class,
        BankStatement::class => BankStatementPolicy::class,
        BankAccount::class => BankAccountPolicy::class,
        PaymentRun::class => PaymentRunPolicy::class,
        ReconciliationException::class => ReconciliationExceptionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
