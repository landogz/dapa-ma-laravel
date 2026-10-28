<?php

namespace App\Services;

use App\Repositories\AnalyticsRepository;
use Carbon\Carbon;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $analyticsRepository,
    ) {
    }

    public function dashboard(int $days = 30): array
    {
        $periodComparison = $this->analyticsRepository->periodComparison($days);
        $current = $periodComparison['current'] ?? [];
        $dailyCounts = $this->analyticsRepository->dailyEventCountsFilled($days);
        $audience = $this->analyticsRepository->audienceSummary($days);
        $peakDay = collect($dailyCounts)->sortByDesc('total')->first();
        $audience['peak_day'] = [
            'date'  => $peakDay['date'] ?? null,
            'total' => (int) ($peakDay['total'] ?? 0),
        ];
        $hourlyCounts = $this->analyticsRepository->hourlyCounts($days);
        $weekdayCounts = $this->analyticsRepository->weekdayCounts($days);
        $devices = $this->analyticsRepository->deviceBreakdown($days);
        $ratings = $this->analyticsRepository->ratingSummary($days);
        $views = (int) ($current['views'] ?? 0);
        $bookmarks = (int) ($current['bookmarks'] ?? 0);
        $shares = (int) ($current['shares'] ?? 0);

        $payload = [
            'range_days'           => $days,
            'by_event_type'        => $this->analyticsRepository->countByEventType($days),
            'top_posts'            => $this->analyticsRepository->topPosts(10, $days),
            'top_rated_posts'      => $this->analyticsRepository->topRatedPosts(10),
            'top_categories'       => $this->analyticsRepository->topCategories(6, $days),
            'ratings'              => $ratings,
            'rating_distribution'  => $this->analyticsRepository->ratingDistribution(),
            'daily_counts'         => $dailyCounts,
            'daily_by_event_type'  => $this->analyticsRepository->dailyCountsByEventType($days),
            'hourly_counts'        => $hourlyCounts,
            'weekday_counts'       => $weekdayCounts,
            'period_comparison'    => $periodComparison,
            'audience'             => $audience,
            'funnel'               => [
                'views'      => $views,
                'bookmarks'  => $bookmarks,
                'shares'     => $shares,
                'engagement' => $views > 0 ? round((($bookmarks + $shares) / $views) * 100, 1) : 0,
            ],
            'recent_events'        => $this->analyticsRepository->recentEvents(40, $days),
            'devices'              => $devices,
            'generated_at'         => now()->toIso8601String(),
        ];

        $payload['insights'] = $this->buildInsights($payload);

        return $payload;
    }

    public function exportCsv(int $days = 30): string
    {
        $rows   = $this->analyticsRepository->allForExport($days);
        $header = ['id', 'event_type', 'post_id', 'post_title', 'user_id', 'user_email', 'platform', 'session_id', 'created_at'];

        $lines = [$this->csvRow($header)];

        foreach ($rows as $row) {
            $lines[] = $this->csvRow([
                $row->id,
                $row->event_type,
                $row->post_id ?? '',
                $row->post->title ?? '',
                $row->user_id ?? '',
                $row->user->email ?? '',
                $row->platform ?? '',
                $row->session_id ?? '',
                optional($row->created_at)?->toIso8601String() ?? '',
            ]);
        }

        return "\u{FEFF}" . implode("\n", $lines);
    }

    private function csvRow(array $columns): string
    {
        return implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $columns));
    }

    private function buildInsights(array $dashboard): array
    {
        $insights = [];
        $current = $dashboard['period_comparison']['current'] ?? [];
        $previous = $dashboard['period_comparison']['previous'] ?? [];
        $label = $dashboard['period_comparison']['label'] ?? 'the previous period';
        $audience = $dashboard['audience'] ?? [];
        $funnel = $dashboard['funnel'] ?? [];

        $viewsCurrent = (int) ($current['views'] ?? 0);
        $viewsPrevious = (int) ($previous['views'] ?? 0);
        $viewsDelta = $viewsCurrent - $viewsPrevious;
        $viewsPercent = $this->percentChange($viewsCurrent, $viewsPrevious);

        if ($viewsPrevious === 0 && $viewsCurrent > 0) {
            $insights[] = [
                'tone' => 'up',
                'icon' => 'fa-arrow-trend-up',
                'text' => sprintf(
                    '%s views recorded; none in %s.',
                    number_format($viewsCurrent),
                    $label
                ),
            ];
        } elseif ($viewsDelta !== 0) {
            $insights[] = [
                'tone' => $viewsDelta > 0 ? 'up' : 'down',
                'icon' => $viewsDelta > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down',
                'text' => sprintf(
                    'Views are %s %s%% versus %s.',
                    $viewsDelta > 0 ? 'up' : 'down',
                    abs($viewsPercent),
                    $label
                ),
            ];
        }

        $peakHour = collect($dashboard['hourly_counts'] ?? [])->sortByDesc('total')->first();

        if ($peakHour && (int) ($peakHour['total'] ?? 0) > 0) {
            $insights[] = [
                'tone' => 'info',
                'icon' => 'fa-clock',
                'text' => sprintf(
                    'Peak hour is %s with %s events.',
                    $this->formatHourLabel((int) $peakHour['hour']),
                    number_format((int) $peakHour['total'])
                ),
            ];
        }

        $peakDay = $audience['peak_day'] ?? [];

        if ((int) ($peakDay['total'] ?? 0) > 0 && ! empty($peakDay['date'])) {
            $insights[] = [
                'tone' => 'info',
                'icon' => 'fa-calendar-day',
                'text' => sprintf(
                    'Busiest day is %s (%s events).',
                    Carbon::parse($peakDay['date'])->format('M j'),
                    number_format((int) $peakDay['total'])
                ),
            ];
        }

        $devices = $dashboard['devices'] ?? [];
        $deviceTotal = (int) ($devices['total'] ?? 0);

        if ($deviceTotal > 0) {
            $leader = collect([
                ['label' => 'Android', 'total' => (int) ($devices['android'] ?? 0)],
                ['label' => 'iOS', 'total' => (int) ($devices['ios'] ?? 0)],
                ['label' => 'Web', 'total' => (int) ($devices['web'] ?? 0)],
            ])->sortByDesc('total')->first();

            $share = $deviceTotal > 0 ? (int) round((($leader['total'] ?? 0) / $deviceTotal) * 100) : 0;

            $insights[] = [
                'tone' => 'info',
                'icon' => 'fa-mobile-screen',
                'text' => sprintf('%s leads the device mix at %s%% of events.', $leader['label'] ?? 'Unknown', $share),
            ];
        }

        $uniqueUsers = (int) ($audience['unique_users'] ?? 0);
        $uniqueSessions = (int) ($audience['unique_sessions'] ?? 0);

        if ($uniqueUsers > 0) {
            $insights[] = [
                'tone' => 'info',
                'icon' => 'fa-users',
                'text' => sprintf(
                    '%s signed-in %s across %s %s (avg %s events/day).',
                    number_format($uniqueUsers),
                    $uniqueUsers === 1 ? 'user' : 'users',
                    number_format($uniqueSessions),
                    $uniqueSessions === 1 ? 'session' : 'sessions',
                    number_format((float) ($audience['avg_per_day'] ?? 0), 1)
                ),
            ];
        }

        $engagement = (float) ($funnel['engagement'] ?? 0);

        if ($engagement > 0) {
            $insights[] = [
                'tone' => $engagement >= 8 ? 'up' : 'neutral',
                'icon' => 'fa-bolt',
                'text' => sprintf(
                    'Engagement rate is %s%% (bookmarks and shares relative to views).',
                    number_format($engagement, 1)
                ),
            ];
        }

        return array_slice($insights, 0, 5);
    }

    private function percentChange(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : 100;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function formatHourLabel(int $hour): string
    {
        $normalized = max(0, min(23, $hour));

        return Carbon::createFromTime($normalized, 0)->format('g A');
    }
}
