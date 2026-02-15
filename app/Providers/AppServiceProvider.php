<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Support subdirectory installs like /flowdesk/public on XAMPP.
        $prefix = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($prefix === '') {
            return;
        }

        Livewire::setScriptRoute(function ($handle) use ($prefix) {
            return Route::get('/'.$prefix.'/livewire/livewire.js', $handle);
        });

        Livewire::setUpdateRoute(function ($handle) use ($prefix) {
            return Route::post('/'.$prefix.'/livewire/update', $handle)
                ->middleware(['web']);
        });
    }
}
