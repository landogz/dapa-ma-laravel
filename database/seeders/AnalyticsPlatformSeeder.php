<?php

namespace Database\Seeders;

use App\Models\AnalyticsEngagementScoreWeight;
use App\Models\AnalyticsFunnelDefinition;
use App\Models\AnalyticsFunnelStep;
use App\Services\Analytics\AnalyticsAnomalyDetector;
use App\Support\Analytics\AnalyticsEventTypes;
use Illuminate\Database\Seeder;

class AnalyticsPlatformSeeder extends Seeder
{
    public function run(): void
    {
        app(AnalyticsAnomalyDetector::class)->ensureDefaultRules();

        $weights = [
            [AnalyticsEventTypes::APP_OPEN, 'App open', 1],
            [AnalyticsEventTypes::SCREEN_VIEW, 'Screen view', 1],
            [AnalyticsEventTypes::PAGE_VIEW, 'Page view', 1],
            [AnalyticsEventTypes::POST_VIEW, 'Post view', 2],
            [AnalyticsEventTypes::POST_LIKE, 'Like', 2],
            ['post_comment', 'Comment', 3],
            [AnalyticsEventTypes::SHARE, 'Share', 5],
            [AnalyticsEventTypes::BOOKMARK, 'Bookmark', 2],
            [AnalyticsEventTypes::SEARCH, 'Search', 1],
            [AnalyticsEventTypes::FEATURE_USE, 'Feature use', 2],
        ];

        foreach ($weights as [$key, $label, $points]) {
            AnalyticsEngagementScoreWeight::query()->updateOrCreate(
                ['event_key' => $key],
                ['label' => $label, 'points' => $points, 'is_active' => true],
            );
        }

        $funnel = AnalyticsFunnelDefinition::query()->updateOrCreate(
            ['key' => 'default_content'],
            [
                'name' => 'Registration to engagement',
                'description' => 'Default sequential journey from first activity to content engagement.',
                'max_window_hours' => 168,
                'is_sequential' => true,
                'is_active' => true,
            ],
        );

        $steps = [
            [1, 'App open', AnalyticsEventTypes::APP_OPEN],
            [2, 'Screen view', AnalyticsEventTypes::SCREEN_VIEW],
            [3, 'Post view', AnalyticsEventTypes::POST_VIEW],
            [4, 'Engagement (like)', AnalyticsEventTypes::POST_LIKE],
        ];

        foreach ($steps as [$order, $name, $type]) {
            AnalyticsFunnelStep::query()->updateOrCreate(
                ['funnel_id' => $funnel->id, 'step_order' => $order],
                ['name' => $name, 'event_type' => $type, 'resource_type' => null],
            );
        }
    }
}
