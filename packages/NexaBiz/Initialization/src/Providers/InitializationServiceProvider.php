<?php

namespace NexaBiz\Initialization\Providers;

use Illuminate\Support\ServiceProvider;

class InitializationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/initialization.php', 'nexabiz');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
