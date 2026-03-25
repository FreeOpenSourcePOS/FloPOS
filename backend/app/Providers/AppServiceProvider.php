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
        // Cloud-edition extensions live in app/Cloud/ (private repo only).
        // This hook is a no-op when that directory does not exist.
        if (config('app.edition') === 'cloud' && class_exists(\App\Cloud\CloudBootstrap::class)) {
            \App\Cloud\CloudBootstrap::boot();
        }
    }
}
