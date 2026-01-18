<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old metric data based on retention policies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting metric cleanup...');

        // Cleanup metrics_raw: delete data older than 24 hours
        $rawCutoff = Carbon::now()->subDay();
        $deletedRaw = DB::table('metrics_raw')
            ->where('timestamp', '<', $rawCutoff)
            ->delete();

        $this->info("Deleted {$deletedRaw} raw metric(s) older than 24 hours");

        // Cleanup metrics_1m: delete data older than 7 days
        $oneMCutoff = Carbon::now()->subDays(7);
        $deleted1m = DB::table('metrics_1m')
            ->where('window_start', '<', $oneMCutoff)
            ->delete();

        $this->info("Deleted {$deleted1m} 1-minute metric(s) older than 7 days");

        // Cleanup metrics_5m: delete data older than 30 days
        $fiveMCutoff = Carbon::now()->subDays(30);
        $deleted5m = DB::table('metrics_5m')
            ->where('window_start', '<', $fiveMCutoff)
            ->delete();

        $this->info("Deleted {$deleted5m} 5-minute metric(s) older than 30 days");

        $totalDeleted = $deletedRaw + $deleted1m + $deleted5m;
        $this->info("Cleanup complete. Total records deleted: {$totalDeleted}");

        return Command::SUCCESS;
    }
}
