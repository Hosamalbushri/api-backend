<?php

namespace App\Console\Commands;

use App\Support\ProductionSettings;
use Illuminate\Console\Command;

class CheckProductionSettingsCommand extends Command
{
    protected $signature = 'nexabiz:check-production';

    protected $description = 'Refuse to boot with unsafe production/staging settings (Python parity).';

    public function handle(): int
    {
        ProductionSettings::assertSafeForEnvironment();
        $this->info('Production settings look safe for APP_ENV='.config('nexabiz.app_env'));

        return self::SUCCESS;
    }
}
