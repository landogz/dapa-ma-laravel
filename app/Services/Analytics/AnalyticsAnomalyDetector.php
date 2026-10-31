<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsAlertRule;
use App\Models\AnalyticsAnomalyAlert;
use App\Repositories\AnalyticsRepository;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Support\Str;

class AnalyticsAnomalyDetector
{
    public function __construct(
        private readonly AnalyticsRepository $analyticsRepository,
    ) {
    }

    public function run(): int
    {
        $this->ensureDefaultRules();
        $created = 0;

        $rules = AnalyticsAlertRule::query()->where('is_active', true)->get();
        foreach ($rules as $rule) {
            $range = AnalyticsDateRange::fromRequest(['range' => (string) $rule->lookback_days]);
            $comparison = $this->analyticsRepository->periodComparison($range);
            $current = $comparison['current'] ?? [];
            $previous = $comparison['previous'] ?? [];

            $currentValue = (float) ($current[$rule->metric_key] ?? 0);
            $previousValue = (float) ($previous[$rule->metric_key] ?? 0);

            if (max($currentValue, $previousValue) < (int) $rule->min_volume) {
                continue;
            }

            $percent = $previousValue == 0.0
                ? ($currentValue > 0 ? 100.0 : 0.0)
                : (($currentValue - $previousValue) / $previousValue) * 100;

            $abs = abs($percent);
            if ($abs < (float) $rule->threshold_percent) {
                continue;
            }

            $direction = $percent >= 0 ? 'up' : 'down';
            if ($rule->direction !== 'either' && $rule->direction !== $direction) {
                continue;
            }

            $fingerprint = hash('sha256', implode('|', [
                $rule->key,
                $range->key,
                now()->toDateString(),
                $direction,
            ]));

            if (AnalyticsAnomalyAlert::query()->where('fingerprint', $fingerprint)->exists()) {
                continue;
            }

            AnalyticsAnomalyAlert::query()->create([
                'rule_id' => $rule->id,
                'title' => $rule->name,
                'description' => sprintf(
                    '%s changed by %.1f%% versus the previous period (%.0f → %.0f).',
                    Str::headline($rule->metric_key),
                    $percent,
                    $previousValue,
                    $currentValue,
                ),
                'metric_key' => $rule->metric_key,
                'previous_value' => $previousValue,
                'current_value' => $currentValue,
                'percent_change' => round($percent, 2),
                'severity' => $rule->severity,
                'status' => 'new',
                'fingerprint' => $fingerprint,
                'detected_at' => now(),
                'context' => [
                    'range' => $range->toArray(),
                    'direction' => $direction,
                ],
            ]);
            $created++;
        }

        return $created;
    }

    public function ensureDefaultRules(): void
    {
        $defaults = [
            [
                'key' => 'views_drop',
                'name' => 'Content views drop',
                'metric_key' => 'views',
                'direction' => 'down',
                'severity' => 'high',
                'threshold_percent' => 30,
                'min_volume' => 20,
                'lookback_days' => 7,
            ],
            [
                'key' => 'views_spike',
                'name' => 'Content views spike',
                'metric_key' => 'views',
                'direction' => 'up',
                'severity' => 'medium',
                'threshold_percent' => 50,
                'min_volume' => 20,
                'lookback_days' => 7,
            ],
            [
                'key' => 'unique_users_drop',
                'name' => 'Active users drop',
                'metric_key' => 'unique_users',
                'direction' => 'down',
                'severity' => 'high',
                'threshold_percent' => 30,
                'min_volume' => 10,
                'lookback_days' => 7,
            ],
            [
                'key' => 'shares_drop',
                'name' => 'Shares drop',
                'metric_key' => 'shares',
                'direction' => 'down',
                'severity' => 'medium',
                'threshold_percent' => 40,
                'min_volume' => 10,
                'lookback_days' => 14,
            ],
        ];

        foreach ($defaults as $row) {
            AnalyticsAlertRule::query()->firstOrCreate(
                ['key' => $row['key']],
                array_merge($row, [
                    'comparison' => 'previous_period',
                    'is_active' => true,
                ]),
            );
        }
    }
}
