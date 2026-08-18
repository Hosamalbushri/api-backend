<?php

namespace NexaBiz\Core\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NexaBiz\Core\Console\Commands\CheckProductionSettingsCommand;
use NexaBiz\Core\Http\Controllers\HealthController;
use NexaBiz\Core\Support\ProductionSettings;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/core.php', 'nexabiz');
    }

    public function boot(): void
    {
        ProductionSettings::assertSafeForEnvironment();

        $this->commands([
            CheckProductionSettingsCommand::class,
        ]);

        Route::get('/', [HealthController::class, 'root']);
        Route::get('/health', HealthController::class);
    }
}
