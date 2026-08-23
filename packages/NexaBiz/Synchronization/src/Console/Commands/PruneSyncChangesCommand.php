<?php

namespace NexaBiz\Synchronization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneSyncChangesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:prune-changes
                            {--company= : Company ID to prune changes for (optional)}
                            {--days=90 : Keep changes newer than this number of days}
                            {--dry-run : Simulate pruning without deleting records}
                            {--buffer=1000 : Keep at least this number of latest changes per company regardless of age}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old change-log records from sync_changes table while preserving active client cursors.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $companyId = $this->option('company');
        $days = (int) $this->option('days');
        $isDryRun = (bool) $this->option('dry-run');
        $buffer = (int) $this->option('buffer');

        $cutoffDate = now()->subDays($days);

        $this->info("Starting sync_changes pruning (Cutoff: {$cutoffDate->toDateTimeString()}, Days: {$days}, Buffer: {$buffer}, Dry-Run: " . ($isDryRun ? 'YES' : 'NO') . ")");

        $query = DB::table('sync_changes')
            ->where('created_at', '<', $cutoffDate);

        if (!empty($companyId)) {
            $query->where('company_id', $companyId);
        }

        // Get candidate companies to enforce buffer per company
        $companies = empty($companyId)
            ? DB::table('sync_changes')->select('company_id')->distinct()->pluck('company_id')->toArray()
            : [$companyId];

        $totalPruned = 0;

        foreach ($companies as $cid) {
            // Find the highest sequence number for this company
            $maxSeq = DB::table('sync_changes')
                ->where('company_id', $cid)
                ->max('sequence');

            if ($maxSeq === null) {
                continue;
            }

            // Safety rule: never prune sequences above (maxSeq - buffer)
            $safeMaxPruneSeq = max(0, $maxSeq - $buffer);

            $pruneQuery = DB::table('sync_changes')
                ->where('company_id', $cid)
                ->where('created_at', '<', $cutoffDate)
                ->where('sequence', '<=', $safeMaxPruneSeq);

            $count = $pruneQuery->count();

            if ($count === 0) {
                continue;
            }

            if ($isDryRun) {
                $this->info("[Dry-Run] Would prune {$count} records for company {$cid} (safe max seq: {$safeMaxPruneSeq})");
            } else {
                $deleted = $pruneQuery->delete();
                $this->info("Pruned {$deleted} records for company {$cid}");
                Log::channel('sync')->info('SYNC_PRUNING_EXECUTED', [
                    'company_id' => $cid,
                    'pruned_count' => $deleted,
                    'cutoff' => $cutoffDate->toIso8601String(),
                    'safe_max_seq' => $safeMaxPruneSeq,
                ]);
                $totalPruned += $deleted;
            }
        }

        $this->info("Pruning complete. Total records " . ($isDryRun ? 'would be pruned' : 'pruned') . ": {$totalPruned}");

        return Command::SUCCESS;
    }
}
