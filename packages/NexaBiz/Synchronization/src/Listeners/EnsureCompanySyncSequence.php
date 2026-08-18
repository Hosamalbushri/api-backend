<?php

namespace NexaBiz\Synchronization\Listeners;

use NexaBiz\Identity\Events\CompanyProvisioned;
use NexaBiz\Synchronization\Models\SyncSequence;

class EnsureCompanySyncSequence
{
    public function handle(CompanyProvisioned $event): void
    {
        if (! SyncSequence::query()->find($event->companyId)) {
            SyncSequence::query()->create([
                'company_id' => $event->companyId,
                'next_value' => 1,
            ]);
        }
    }
}
