<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExpenseAttachmentDownloadController;
use App\Http\Controllers\UserAvatarController;
use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardShell;
use App\Livewire\Organization\ApprovalWorkflowsPage;
use App\Livewire\Organization\DepartmentsPage;
use App\Livewire\Organization\TeamPage;
use App\Livewire\Settings\CompanySetup;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/settings/company/setup', CompanySetup::class)->name('settings.company.setup');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'company.context'])->group(function (): void {
    Route::get('/dashboard', DashboardShell::class)->name('dashboard');

    Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
        Route::get('/index', DashboardShell::class)->name('index');
    });

    Route::prefix('requests')->name('requests.')->group(function (): void {
        Route::view('/', 'app.requests.index')->name('index');
    });

    Route::prefix('expenses')->name('expenses.')->group(function (): void {
        Route::view('/', 'app.expenses.index')->name('index');
        Route::get('/attachments/{attachment}/download', ExpenseAttachmentDownloadController::class)
            ->name('attachments.download');
    });

    Route::prefix('vendors')->name('vendors.')->group(function (): void {
        Route::view('/', 'app.vendors.index')->name('index');
    });

    Route::prefix('budgets')->name('budgets.')->group(function (): void {
        Route::view('/', 'app.budgets.index')->name('index');
    });

    Route::prefix('assets')->name('assets.')->group(function (): void {
        Route::view('/', 'app.assets.index')->name('index');
    });

    Route::prefix('departments')->name('departments.')->group(function (): void {
        Route::get('/', DepartmentsPage::class)->name('index');
    });

    Route::prefix('team')->name('team.')->group(function (): void {
        Route::get('/', TeamPage::class)->name('index');
    });

    Route::get('/users/{user}/avatar', UserAvatarController::class)->name('users.avatar');

    Route::prefix('approval-workflows')->name('approval-workflows.')->group(function (): void {
        Route::get('/', ApprovalWorkflowsPage::class)->name('index');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::view('/', 'app.settings.index')->name('index');
        Route::get('/organization', function () {
            abort_unless(auth()->user()?->role === UserRole::Owner->value, 403);

            return redirect()->route('departments.index');
        })->name('organization');
    });
});

require __DIR__.'/auth.php';
