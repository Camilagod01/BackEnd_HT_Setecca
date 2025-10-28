<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        try {
            app(\App\Services\HolidaySetupService::class)->ensure();
        } catch (\Throwable $e) {
            \Log::warning('[Holidays ensure] '.$e->getMessage());
        }
    }
}
