<?php

namespace NexaBiz\Audit\Providers;

use Illuminate\Support\ServiceProvider;
use NexaBiz\Audit\Contracts\AuditWriter;
use NexaBiz\Audit\Services\AuditService;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditWriter::class, AuditService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
