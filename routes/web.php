<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExpenseAttachmentDownloadController;
use App\Livewire\Dashboard\DashboardShell;
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

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::view('/', 'app.settings.index')->name('index');
    });
});

require __DIR__.'/auth.php';
