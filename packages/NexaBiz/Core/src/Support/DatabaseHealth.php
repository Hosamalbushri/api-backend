<?php

namespace NexaBiz\Core\Support;

use Illuminate\Support\Facades\DB;

class DatabaseHealth
{
    public function ok(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
