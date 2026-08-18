<?php

namespace App\Providers;

use App\Support\ProductionSettings;
use Database\Seeders\IdentitySeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ProductionSettings::assertSafeForEnvironment();

        if ($this->app->runningInConsole() || $this->app->environment('testing')) {
            return;
        }

        if (! config('nexabiz.seed_on_boot')) {
            return;
        }

        try {
            if (! Schema::hasTable('permissions')) {
                return;
            }
            (new IdentitySeeder)->run();
        } catch (\Throwable) {
            // Database may be unavailable during first boot / missing pdo_pgsql.
        }
    }
}
