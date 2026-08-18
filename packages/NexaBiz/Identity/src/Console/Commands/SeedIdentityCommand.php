<?php

namespace NexaBiz\Identity\Console\Commands;

use Illuminate\Console\Command;
use NexaBiz\Identity\Database\Seeders\IdentitySeeder;

class SeedIdentityCommand extends Command
{
    protected $signature = 'nexabiz:identity:seed';

    protected $description = 'Idempotently seed permissions, system roles, demo company, and admin users.';

    public function handle(IdentitySeeder $seeder): int
    {
        $seeder->run();
        $this->info('Identity seed complete.');

        return self::SUCCESS;
    }
}
