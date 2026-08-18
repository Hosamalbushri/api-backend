<?php

namespace NexaBiz\Synchronization\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NexaBiz\Identity\Events\CompanyProvisioned;
use NexaBiz\Synchronization\Contracts\SyncEngine;
use NexaBiz\Synchronization\Listeners\EnsureCompanySyncSequence;
use NexaBiz\Synchronization\Services\SyncService;

class SynchronizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/synchronization.php', 'nexabiz');
        $this->app->singleton(SyncEngine::class, SyncService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        Event::listen(CompanyProvisioned::class, EnsureCompanySyncSequence::class);
    }
}
