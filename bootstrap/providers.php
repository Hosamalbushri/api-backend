<?php

use App\Providers\AppServiceProvider;
use NexaBiz\Audit\Providers\AuditServiceProvider;
use NexaBiz\Core\Providers\CoreServiceProvider;
use NexaBiz\Identity\Providers\IdentityServiceProvider;
use NexaBiz\Synchronization\Providers\SynchronizationServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    SynchronizationServiceProvider::class,
];
