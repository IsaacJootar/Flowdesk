<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExpenseAttachmentDownloadController;
use App\Http\Controllers\RequestAttachmentDownloadController;
use App\Http\Controllers\UserAvatarController;
use App\Http\Controllers\ExecutionWebhookController;
use App\Http\Controllers\VendorInvoiceAttachmentDownloadController;
use App\Http\Controllers\VendorInvoicePaymentAttachmentDownloadController;
use App\Http\Controllers\VendorStatementCsvExportController;
use App\Http\Controllers\VendorStatementPrintController;
use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardShell;
use App\Livewire\Execution\ExecutionHealthPage;
use App\Livewire\Execution\ExecutionUsageGuidePage;
use App\Livewire\Execution\PayoutReadyQueuePage;
use App\Livewire\Assets\AssetReportsPage;
use App\Livewire\Organization\ApprovalWorkflowsPage;
use App\Livewire\Organization\DepartmentsPage;
use App\Livewire\Organization\TeamPage;
use App\Livewire\Operations\OperationsControlDeskPage;
use App\Livewire\Operations\ApprovalOperationsDeskPage;
use App\Livewire\Operations\VendorPayablesDeskPage;
use App\Livewire\Operations\PeriodCloseDeskPage;
use App\Livewire\Reports\ReportsCenterPage;
use App\Livewire\Requests\RequestCommunicationsPage;
use App\Livewire\Requests\RequestCommunicationsGuidePage;
use App\Livewire\Requests\RequestLifecycleDeskPage;
use App\Livewire\Requests\RequestLifecycleGuidePage;
use App\Livewire\Requests\RequestReportsPage;
use App\Livewire\Settings\CommunicationSettingsPage;
use App\Livewire\Settings\CompanySetup;
use App\Livewire\Settings\AssetControlsPage;
use App\Livewire\Settings\ApprovalTimingControlsPage;
use App\Livewire\Settings\ExpenseControlsPage;
use App\Livewire\Settings\ProcurementControlsPage;
use App\Livewire\Settings\TreasuryControlsPage;
use App\Livewire\Settings\RequestConfigurationPage;
use App\Livewire\Settings\TenantDetailsPage;
use App\Livewire\Settings\TenantManagementPage;
use App\Livewire\Settings\VendorControlsPage;
use App\Livewire\Platform\PlatformUsersPage;
use App\Livewire\Platform\PlatformDashboardPage;
use App\Livewire\Platform\PlatformOperationsHubPage;
use App\Livewire\Platform\TenantExecutionModePage;
use App\Livewire\Platform\TenantExecutionPolicyPage;
use App\Livewire\Platform\TenantPlanEntitlementsPage;
use App\Livewire\Platform\TenantProfilePage;
use App\Livewire\Platform\ExecutionOperationsPage;
use App\Livewire\Platform\ExecutionTestChecklistPage;
use App\Livewire\Platform\IncidentHistoryPage;
use App\Livewire\Platform\PilotRolloutKpiPage;
use App\Livewire\Vendors\VendorDetailsPage;
use App\Livewire\Vendors\VendorReportsPage;
use App\Livewire\Treasury\TreasuryCashPositionPage;
use App\Livewire\Procurement\ProcurementReleaseDeskPage;
use App\Livewire\Procurement\ProcurementReleaseGuidePage;
use App\Livewire\Treasury\TreasuryPaymentRunsPage;
use App\Livewire\Treasury\TreasuryReconciliationExceptionsPage;
use App\Livewire\Treasury\TreasuryReconciliationGuidePage;
use App\Livewire\Treasury\TreasuryReconciliationPage;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::redirect('/', '/dashboard');
// Provider webhooks are external callbacks, so CSRF is intentionally disabled for this endpoint.
Route::post('/webhooks/execution/{provider}', ExecutionWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.execution');

Route::middleware('auth')->group(function (): void {
    Route::get('/settings/company/setup', CompanySetup::class)->name('settings.company.setup');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'platform.access'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/', PlatformDashboardPage::class)->name('dashboard');
    Route::get('/tenants', TenantManagementPage::class)->name('tenants');
    Route::get('/tenants/{company}/plan-entitlements', TenantPlanEntitlementsPage::class)->name('tenants.plan-entitlements');
    Route::get('/tenants/{company}/billing', TenantDetailsPage::class)->name('tenants.billing');
    Route::get('/tenants/{company}/execution-mode', TenantExecutionModePage::class)->name('tenants.execution-mode');
    Route::get('/tenants/{company}/execution-policy', TenantExecutionPolicyPage::class)->name('tenants.execution-policy');
    Route::get('/tenants/{company}', TenantProfilePage::class)->name('tenants.show');
    Route::get('/users', PlatformUsersPage::class)->name('users');
    Route::get('/operations', PlatformOperationsHubPage::class)->name('operations.hub');
    Route::get('/operations/execution', ExecutionOperationsPage::class)->name('operations.execution');
    Route::get('/operations/execution-checklist', ExecutionTestChecklistPage::class)->name('operations.execution-checklist');
    Route::get('/operations/incident-history', IncidentHistoryPage::class)->name('operations.incident-history');
    Route::get('/operations/pilot-rollout', PilotRolloutKpiPage::class)->name('operations.pilot-rollout');
});

Route::middleware(['auth', 'company.context'])->group(function (): void {
    Route::get('/dashboard', DashboardShell::class)->name('dashboard');
    Route::get('/operations/control-desk', OperationsControlDeskPage::class)->name('operations.control-desk');
    Route::get('/operations/approval-desk', ApprovalOperationsDeskPage::class)->name('operations.approval-desk');
    Route::get('/operations/vendor-payables-desk', VendorPayablesDeskPage::class)->name('operations.vendor-payables-desk');
    Route::get('/operations/period-close-desk', PeriodCloseDeskPage::class)->name('operations.period-close-desk');
    Route::get('/execution/health', ExecutionHealthPage::class)->name('execution.health');
    Route::get('/execution/payout-ready', PayoutReadyQueuePage::class)->name('execution.payout-ready');
    Route::get('/execution/help', ExecutionUsageGuidePage::class)->name('execution.help');
    Route::get('/reports', ReportsCenterPage::class)->middleware('module.enabled:reports')->name('reports.index');

    Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
        Route::get('/index', DashboardShell::class)->name('index');
    });

    Route::prefix('requests')->middleware('module.enabled:requests')->name('requests.')->group(function (): void {
        Route::view('/', 'app.requests.index')->name('index');
        Route::get('/lifecycle-desk', RequestLifecycleDeskPage::class)->name('lifecycle-desk');
        Route::get('/lifecycle-help', RequestLifecycleGuidePage::class)->name('lifecycle-help');
        Route::get('/communications', RequestCommunicationsPage::class)->middleware('module.enabled:communications')->name('communications');
        Route::get('/communications/help', RequestCommunicationsGuidePage::class)->middleware('module.enabled:communications')->name('communications-help');
        Route::get('/reports', RequestReportsPage::class)->middleware('module.enabled:reports')->name('reports');
        Route::get('/attachments/{attachment}/download', RequestAttachmentDownloadController::class)
            ->name('attachments.download');
    });

    Route::prefix('expenses')->middleware('module.enabled:expenses')->name('expenses.')->group(function (): void {
        Route::view('/', 'app.expenses.index')->name('index');
        Route::get('/attachments/{attachment}/download', ExpenseAttachmentDownloadController::class)
            ->name('attachments.download');
    });

    Route::prefix('vendors')->middleware('module.enabled:vendors')->name('vendors.')->group(function (): void {
        Route::view('/', 'app.vendors.index')->name('index');
        Route::get('/reports', VendorReportsPage::class)->middleware('module.enabled:reports')->name('reports');
        Route::get('/{vendor}/statement/export.csv', VendorStatementCsvExportController::class)
            ->name('statement.export.csv');
        Route::get('/{vendor}/statement/print', VendorStatementPrintController::class)
            ->name('statement.print');
        Route::get('/attachments/invoices/{attachment}/download', VendorInvoiceAttachmentDownloadController::class)
            ->name('attachments.invoices.download');
        Route::get('/attachments/payments/{attachment}/download', VendorInvoicePaymentAttachmentDownloadController::class)
            ->name('attachments.payments.download');
        Route::get('/{vendor}', VendorDetailsPage::class)->name('show');
    });

    Route::prefix('budgets')->middleware('module.enabled:budgets')->name('budgets.')->group(function (): void {
        Route::view('/', 'app.budgets.index')->name('index');
    });

    Route::prefix('assets')->middleware('module.enabled:assets')->name('assets.')->group(function (): void {
        Route::view('/', 'app.assets.index')->name('index');
        Route::get('/reports', AssetReportsPage::class)->middleware('module.enabled:reports')->name('reports');
    });
    Route::prefix('procurement')->middleware('module.enabled:procurement')->name('procurement.')->group(function (): void {
        Route::get('/release-desk', ProcurementReleaseDeskPage::class)->name('release-desk');
        Route::get('/release-help', ProcurementReleaseGuidePage::class)->name('release-help');
        Route::view('/orders', 'app.procurement.orders')->name('orders');
        Route::view('/receipts', 'app.procurement.receipts')->name('receipts');
        Route::view('/match-exceptions', 'app.procurement.match-exceptions')->name('match-exceptions');
    });

    Route::prefix('treasury')->middleware('module.enabled:treasury')->name('treasury.')->group(function (): void {
        Route::get('/reconciliation', TreasuryReconciliationPage::class)->name('reconciliation');
        Route::get('/reconciliation/help', TreasuryReconciliationGuidePage::class)->name('reconciliation-help');
        Route::get('/reconciliation/exceptions', TreasuryReconciliationExceptionsPage::class)->name('reconciliation-exceptions');
        Route::get('/payment-runs', TreasuryPaymentRunsPage::class)->name('payment-runs');
        Route::get('/cash-position', TreasuryCashPositionPage::class)->name('cash-position');
    });

    Route::prefix('departments')->name('departments.')->group(function (): void {
        Route::get('/', DepartmentsPage::class)->name('index');
    });

    Route::prefix('team')->name('team.')->group(function (): void {
        Route::get('/', TeamPage::class)->name('index');
    });

    Route::get('/users/{user}/avatar', UserAvatarController::class)->name('users.avatar');

    Route::prefix('approval-workflows')->middleware('module.enabled:requests')->name('approval-workflows.')->group(function (): void {
        Route::get('/', ApprovalWorkflowsPage::class)->name('index');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', function () {
            abort_unless(\Illuminate\Support\Facades\Auth::user()?->role === UserRole::Owner->value, 403);

            return view('app.settings.index');
        })->name('index');
        Route::get('/communications', CommunicationSettingsPage::class)->middleware('module.enabled:communications')->name('communications');
        Route::get('/request-configuration', RequestConfigurationPage::class)->middleware('module.enabled:requests')->name('request-configuration');
        Route::get('/approval-timing-controls', ApprovalTimingControlsPage::class)->middleware('module.enabled:requests')->name('approval-timing-controls');
        Route::get('/expense-controls', ExpenseControlsPage::class)->middleware('module.enabled:expenses')->name('expense-controls');
        Route::get('/asset-controls', AssetControlsPage::class)->middleware('module.enabled:assets')->name('asset-controls');
        Route::get('/vendor-controls', VendorControlsPage::class)->middleware('module.enabled:vendors')->name('vendor-controls');
        Route::get('/procurement-controls', ProcurementControlsPage::class)->middleware('module.enabled:procurement')->name('procurement-controls');
        Route::get('/treasury-controls', TreasuryControlsPage::class)->middleware('module.enabled:treasury')->name('treasury-controls');
        Route::get('/organization', function () {
            abort_unless(\Illuminate\Support\Facades\Auth::user()?->role === UserRole::Owner->value, 403);

            return redirect()->route('departments.index');
        })->name('organization');
    });
});

require __DIR__.'/auth.php';







