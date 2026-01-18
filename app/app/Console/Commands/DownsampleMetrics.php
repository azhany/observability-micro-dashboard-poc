<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DownsampleMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:downsample {resolution : The resolution to downsample (1m or 5m)} {--window-start= : Optional window start timestamp for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downsample raw metrics into 1-minute or 5-minute averages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $resolution = $this->argument('resolution');

        if (! in_array($resolution, ['1m', '5m'])) {
            $this->error('Resolution must be either 1m or 5m');

            return Command::FAILURE;
        }

        $minutes = $resolution === '1m' ? 1 : 5;
        $targetTable = "metrics_{$resolution}";

        // Calculate the time window for aggregation
        if ($this->option('window-start')) {
            // Use provided window start for testing
            $windowStart = Carbon::parse($this->option('window-start'));
            $windowEnd = $windowStart->copy()->addMinutes($minutes);
        } else {
            // We process the previous complete window to ensure all data is available
            $windowEnd = Carbon::now()->startOfMinute();
            $windowStart = $windowEnd->copy()->subMinutes($minutes);

            // For 5m, align to 5-minute boundaries (0, 5, 10, 15, etc.)
            if ($resolution === '5m') {
                $minute = $windowStart->minute;
                $alignedMinute = floor($minute / 5) * 5;
                $windowStart->minute($alignedMinute)->second(0);
                $windowEnd = $windowStart->copy()->addMinutes(5);
            }
        }

        $this->info("Processing {$resolution} rollup for window: {$windowStart} to {$windowEnd}");

        // Aggregate data from metrics_raw
        $aggregates = DB::table('metrics_raw')
            ->select(
                'tenant_id',
                'metric_name',
                DB::raw('AVG(value) as avg_value')
            )
            ->whereBetween('timestamp', [$windowStart, $windowEnd])
            ->groupBy('tenant_id', 'metric_name')
            ->get();

        if ($aggregates->isEmpty()) {
            $this->info('No data to aggregate for this window');

            return Command::SUCCESS;
        }

        $insertedCount = 0;

        foreach ($aggregates as $aggregate) {
            // Insert or update the aggregated data
            DB::table($targetTable)->updateOrInsert(
                [
                    'tenant_id' => $aggregate->tenant_id,
                    'metric_name' => $aggregate->metric_name,
                    'window_start' => $windowStart,
                ],
                [
                    'avg_value' => $aggregate->avg_value,
                ]
            );
            $insertedCount++;
        }

        $this->info("Successfully aggregated {$insertedCount} metric(s) into {$targetTable}");

        return Command::SUCCESS;
    }
}
