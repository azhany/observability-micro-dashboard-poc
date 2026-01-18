<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Metric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateAlertRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $count = 0;

        foreach (AlertRule::cursor() as $rule) {
            $this->evaluateRule($rule);
            $count++;
        }

        Log::info('Alert rule evaluation completed', [
            'rules_evaluated' => $count,
        ]);
    }

    /**
     * Evaluate a single alert rule against recent metrics.
     */
    protected function evaluateRule(AlertRule $rule): void
    {
        $lookbackWindow = now()->subSeconds($rule->duration + 60);

        $recentMetrics = Metric::where('tenant_id', $rule->tenant_id)
            ->where('metric_name', $rule->metric_name)
            ->where('timestamp', '>=', $lookbackWindow)
            ->orderBy('timestamp', 'desc')
            ->get();

        if ($recentMetrics->isEmpty()) {
            return;
        }

        $currentAlert = Alert::where('alert_rule_id', $rule->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $isThresholdBreached = $this->checkThreshold(
            $recentMetrics->first()->value,
            $rule->operator,
            $rule->threshold
        );

        $this->updateAlertState($rule, $currentAlert, $isThresholdBreached, $recentMetrics);
    }

    /**
     * Check if a metric value breaches the threshold based on the operator.
     */
    protected function checkThreshold(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $value > $threshold,
            '<' => $value < $threshold,
            '=' => $value == $threshold,
            '>=' => $value >= $threshold,
            '<=' => $value <= $threshold,
            default => false,
        };
    }

    /**
     * Update the alert state based on the current state and threshold status.
     */
    protected function updateAlertState(
        AlertRule $rule,
        ?Alert $currentAlert,
        bool $isThresholdBreached,
        $recentMetrics
    ): void {
        $now = now();

        if (! $isThresholdBreached) {
            if ($currentAlert && in_array($currentAlert->state, ['PENDING', 'FIRING'])) {
                Alert::create([
                    'tenant_id' => $rule->tenant_id,
                    'alert_rule_id' => $rule->id,
                    'state' => 'OK',
                    'started_at' => $now,
                    'last_checked_at' => $now,
                ]);

                Log::info('Alert resolved', [
                    'rule_id' => $rule->id,
                    'previous_state' => $currentAlert->state,
                ]);
            }

            return;
        }

        if (! $currentAlert || $currentAlert->state === 'OK') {
            Alert::create([
                'tenant_id' => $rule->tenant_id,
                'alert_rule_id' => $rule->id,
                'state' => 'PENDING',
                'started_at' => $now,
                'last_checked_at' => $now,
            ]);

            Log::info('Alert moved to PENDING', [
                'rule_id' => $rule->id,
                'metric_name' => $rule->metric_name,
            ]);

            return;
        }

        if ($currentAlert->state === 'PENDING') {
            $currentAlert->update(['last_checked_at' => $now]);

            $breachDuration = $currentAlert->started_at->diffInSeconds($now);

            if ($breachDuration >= $rule->duration) {
                Alert::create([
                    'tenant_id' => $rule->tenant_id,
                    'alert_rule_id' => $rule->id,
                    'state' => 'FIRING',
                    'started_at' => $currentAlert->started_at,
                    'last_checked_at' => $now,
                ]);

                Log::warning('Alert is FIRING', [
                    'rule_id' => $rule->id,
                    'metric_name' => $rule->metric_name,
                    'duration' => $breachDuration,
                ]);
            }

            return;
        }

        if ($currentAlert->state === 'FIRING') {
            $currentAlert->update(['last_checked_at' => $now]);

            Log::debug('Alert remains FIRING', [
                'rule_id' => $rule->id,
                'metric_name' => $rule->metric_name,
            ]);
        }
    }
}
