<?php

namespace NexaBiz\Identity\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use NexaBiz\Identity\Console\Commands\SeedIdentityCommand;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;
use NexaBiz\Identity\Http\Middleware\AuthenticateApi;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/identity.php', 'nexabiz');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        $this->commands([SeedIdentityCommand::class]);
        $this->app['router']->aliasMiddleware('auth.api', AuthenticateApi::class);

        $this->app->booted(fn () => $this->seedOnBoot());
    }

    private function seedOnBoot(): void
    {
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
