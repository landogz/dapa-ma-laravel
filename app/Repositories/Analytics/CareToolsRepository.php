<?php

namespace App\Repositories\Analytics;

use App\Models\AnalyticsEvent;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsEventTypes;
use Illuminate\Support\Facades\DB;

class CareToolsRepository
{
    /**
     * Count-only care and help-tool usage. No journal/mood/check bodies.
     *
     * @return array{totals: array<string, int>, features: array<string, int>}
     */
    public function dashboard(AnalyticsDateRange $range): array
    {
        $counts = AnalyticsEvent::query()
            ->inWindow($range->from, $range->to)
            ->whereIn('event_type', AnalyticsEventTypes::careToolTypes())
            ->select('event_type', DB::raw('count(*) as total'))
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->map(fn ($total) => (int) $total)
            ->all();

        $features = AnalyticsEvent::query()
            ->inWindow($range->from, $range->to)
            ->where('event_type', AnalyticsEventTypes::FEATURE_USE)
            ->whereNotNull('metadata')
            ->get(['metadata'])
            ->reduce(function (array $carry, AnalyticsEvent $event) {
                $feature = trim((string) data_get($event->metadata, 'feature', ''));
                if ($feature === '') {
                    return $carry;
                }
                $carry[$feature] = ($carry[$feature] ?? 0) + 1;

                return $carry;
            }, []);

        return [
            'totals' => [
                'journal_creates' => (int) ($counts[AnalyticsEventTypes::JOURNAL_CREATE] ?? 0),
                'care_checks' => (int) ($counts[AnalyticsEventTypes::CARE_CHECK_SUBMIT] ?? 0),
                'mood_checkins' => (int) ($counts[AnalyticsEventTypes::MOOD_CHECKIN] ?? 0),
                'calm_sessions' => (int) ($counts[AnalyticsEventTypes::CALM_COMPLETE] ?? 0),
                'color_fun_opens' => (int) ($counts[AnalyticsEventTypes::COLOR_FUN_OPEN] ?? 0),
                'color_fun_completes' => (int) ($counts[AnalyticsEventTypes::COLOR_FUN_COMPLETE] ?? 0),
                'notification_opens' => (int) ($counts[AnalyticsEventTypes::NOTIFICATION_OPEN] ?? 0),
                'contest_submits' => (int) ($counts[AnalyticsEventTypes::CONTEST_SUBMIT] ?? 0),
                'feature_uses' => (int) ($counts[AnalyticsEventTypes::FEATURE_USE] ?? 0),
                'toolkit_opens' => (int) ($features['care_toolkit'] ?? 0),
                'support_opens' => (int) ($features['care_support'] ?? 0),
                'kid_listo_opens' => (int) ($features['kid_listo'] ?? 0),
                'sticker_uses' => (int) ($features['sticker'] ?? 0),
            ],
            'features' => $features,
        ];
    }
}
