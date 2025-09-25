<?php

namespace App\Providers;

use App\Models\Module;
use App\Models\ModuleField;
use App\Observers\ModuleFieldObserver;
use App\Observers\ModuleObserver;
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
        Module::observe(ModuleObserver::class);
        ModuleField::observe(ModuleFieldObserver::class);
    }
}
