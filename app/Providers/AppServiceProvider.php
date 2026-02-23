<?php

namespace App\Providers;

use App\Services\RequestCommunication\Sms\NullSmsProvider;
use App\Services\RequestCommunication\Sms\SmsProvider;
use App\Services\RequestCommunication\Sms\TermiiSmsProvider;
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
        $this->app->singleton(SmsProvider::class, function () {
            $provider = strtolower((string) config('services.sms.provider', 'placeholder'));

            return match ($provider) {
                'termii' => new TermiiSmsProvider(),
                default => new NullSmsProvider(),
            };
        });
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
