<?php

namespace App\Services\Analytics;

use App\Repositories\AnalyticsRepository;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AnalyticsReportExporter
{
    private const BRAND_NAVY = '123A60';

    private const BRAND_BLUE = '055498';

    private const BRAND_GOLD = 'FBD116';

    private const BRAND_SLATE = 'F8FAFC';

    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AnalyticsRepository $analyticsRepository,
    ) {
    }

    /**
     * @return array{body: string, filename: string, content_type: string, extension: string}
     */
    public function export(string $reportType, string $format, AnalyticsDateRange $range): array
    {
        $format = in_array($format, ['csv', 'xlsx', 'pdf'], true) ? $format : 'csv';
        $reportType = $this->normalizeReportType($reportType);
        $dataset = $this->dataset($reportType, $range);

        $extension = match ($format) {
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };

        $body = match ($format) {
            'xlsx' => $this->toExcel($dataset),
            'pdf' => $this->toPdf($dataset),
            default => $this->toCsv($dataset),
        };

        $contentType = match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'text/csv; charset=UTF-8',
        };

        $filename = sprintf(
            'dape-ma-analytics-%s-%s-%s.%s',
            $reportType,
            $range->key,
            now()->format('Y-m-d-His'),
            $extension,
        );

        return [
            'body' => $body,
            'filename' => $filename,
            'content_type' => $contentType,
            'extension' => $extension,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'summary', 'label' => 'Overview (detailed + charts)'],
            ['key' => 'events', 'label' => 'Event log (detailed)'],
            ['key' => 'demographics', 'label' => 'Demographics (detailed + charts)'],
            ['key' => 'users', 'label' => 'Users (detailed)'],
            ['key' => 'leaderboard', 'label' => 'Top users leaderboard (detailed + charts)'],
            ['key' => 'retention', 'label' => 'Retention cohorts (detailed)'],
            ['key' => 'mobile', 'label' => 'Mobile / devices (detailed + charts)'],
            ['key' => 'content', 'label' => 'Content performance (detailed + charts)'],
            ['key' => 'journeys', 'label' => 'Journeys / funnel (detailed + charts)'],
            ['key' => 'alerts', 'label' => 'Anomaly alerts (detailed)'],
            ['key' => 'complete', 'label' => 'Complete pack (all sections + charts)'],
        ];
    }

    private function normalizeReportType(string $reportType): string
    {
        return match ($reportType) {
            'traffic', 'summary' => 'summary',
            'posts' => 'content',
            'geography', 'demographics' => 'demographics',
            'user_activity', 'sessions' => 'users',
            'complete' => 'complete',
            'events', 'users', 'leaderboard', 'retention', 'mobile', 'content', 'journeys', 'alerts' => $reportType,
            default => 'events',
        };
    }

    /**
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     sheets: list<array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array{title: string, category_col: int, value_col: int}|null}>,
     *     charts: list<array{title: string, items: list<array{label: string, value: float|int}>}>
     * }
     */
    private function dataset(string $reportType, AnalyticsDateRange $range): array
    {
        $subtitle = 'Detailed report · Range: '.$range->label
            .' · '.$range->from->toDateString().' to '.$range->to->toDateString()
            .' · Includes tables'
            .(in_array($reportType, ['summary', 'mobile', 'content', 'complete'], true) ? ' and charts' : '');

        return match ($reportType) {
            'summary' => [
                'title' => 'DAPE-MA Analytics — Overview (detailed)',
                'subtitle' => $subtitle,
                'sheets' => $this->summarySheets($range),
                'charts' => $this->summaryCharts($range),
            ],
            'demographics' => [
                'title' => 'DAPE-MA Analytics — Demographics (detailed)',
                'subtitle' => $subtitle.' · Profile Region / Province / City',
                'sheets' => $this->demographicsSheets($range),
                'charts' => $this->demographicsCharts($range),
            ],
            'users' => [
                'title' => 'DAPE-MA Analytics — Users (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->usersSheet($range)],
                'charts' => [],
            ],
            'leaderboard' => [
                'title' => 'DAPE-MA Analytics — Top users leaderboard (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->leaderboardSheet($range)],
                'charts' => $this->leaderboardCharts($range),
            ],
            'retention' => [
                'title' => 'DAPE-MA Analytics — Retention (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->retentionSheet($range)],
                'charts' => [],
            ],
            'mobile' => [
                'title' => 'DAPE-MA Analytics — Mobile / devices (detailed)',
                'subtitle' => $subtitle,
                'sheets' => $this->mobileSheets($range),
                'charts' => $this->mobileCharts($range),
            ],
            'content' => [
                'title' => 'DAPE-MA Analytics — Content performance (detailed)',
                'subtitle' => $subtitle,
                'sheets' => $this->contentSheets($range),
                'charts' => $this->contentCharts($range),
            ],
            'journeys' => [
                'title' => 'DAPE-MA Analytics — Journeys (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->journeysSheet($range)],
                'charts' => $this->journeysCharts($range),
            ],
            'alerts' => [
                'title' => 'DAPE-MA Analytics — Anomaly alerts (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->alertsSheet()],
                'charts' => [],
            ],
            'complete' => [
                'title' => 'DAPE-MA Analytics — Complete pack (detailed)',
                'subtitle' => $subtitle,
                'sheets' => array_merge(
                    $this->summarySheets($range),
                    $this->demographicsSheets($range),
                    [$this->usersSheet($range)],
                    [$this->leaderboardSheet($range)],
                    [$this->retentionSheet($range)],
                    $this->contentSheets($range),
                    $this->mobileSheets($range),
                    [$this->journeysSheet($range)],
                    [$this->alertsSheet()],
                ),
                'charts' => array_merge(
                    $this->summaryCharts($range),
                    $this->mobileCharts($range),
                    $this->contentCharts($range),
                    $this->demographicsCharts($range),
                ),
            ],
            default => [
                'title' => 'DAPE-MA Analytics — Event log (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->eventsSheet($range)],
                'charts' => [],
            ],
        };
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}>
     */
    private function summarySheets(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $kpis = $dashboard['kpis'] ?? [];
        $current = $dashboard['period_comparison']['current'] ?? [];
        $previous = $dashboard['period_comparison']['previous'] ?? [];
        $periodLabel = $dashboard['period_comparison']['label'] ?? 'previous period';
        $funnel = $dashboard['funnel'] ?? [];
        $audience = $dashboard['audience'] ?? [];
        $devices = $dashboard['devices'] ?? [];
        $hourlyByMetric = $dashboard['hourly_by_metric']['series'] ?? [];

        $kpiRows = [
            ['Registered users', $kpis['registered_users'] ?? 0, '—', 'App accounts'],
            ['New users', $kpis['new_users'] ?? 0, $previous['new_users'] ?? '—', 'Registered in range'],
            ['Daily active users', $kpis['dau'] ?? 0, '—', 'Last 1 day'],
            ['Weekly active users', $kpis['wau'] ?? 0, '—', 'Last 7 days'],
            ['Monthly active users', $kpis['mau'] ?? 0, '—', 'Last 30 days'],
            ['App opens', $kpis['app_opens'] ?? 0, $previous['app_opens'] ?? '—', 'app_open events'],
            ['Sessions', $current['unique_sessions'] ?? ($audience['unique_sessions'] ?? 0), $previous['unique_sessions'] ?? '—', 'Unique sessions'],
            ['Avg session (seconds)', $kpis['avg_session_duration_seconds'] ?? 0, '—', 'From analytics_sessions'],
            ['Total events', $current['total_events'] ?? ($audience['total_events'] ?? 0), $previous['total_events'] ?? 0, 'All tracked events'],
            ['Unique users (events)', $current['unique_users'] ?? ($audience['unique_users'] ?? 0), $previous['unique_users'] ?? 0, 'Signed-in engagers'],
            ['Views', $current['views'] ?? 0, $previous['views'] ?? 0, 'Content views'],
            ['Bookmarks', $current['bookmarks'] ?? 0, $previous['bookmarks'] ?? 0, 'Bookmark events'],
            ['Shares', $current['shares'] ?? 0, $previous['shares'] ?? 0, 'Share events'],
            ['Searches', $current['searches'] ?? 0, $previous['searches'] ?? 0, 'Search events'],
            ['Engagement rate %', $funnel['engagement'] ?? 0, '—', '(bookmarks + shares) / views'],
            ['Relational engagements', $funnel['relational_engagements'] ?? ($kpis['relational_engagements'] ?? 0), '—', 'Likes/comments/bookmarks/shares'],
            ['Avg events / day', $audience['avg_per_day'] ?? 0, '—', 'Volume / day span'],
            ['Peak day', $audience['peak_day']['date'] ?? '—', $audience['peak_day']['total'] ?? 0, 'Busiest calendar day'],
            ['Comparison window', $periodLabel, '—', 'Previous period label'],
        ];

        $daily = collect($dashboard['daily_counts'] ?? [])->values();
        $dailyMax = max(1, (int) $daily->max(fn ($row) => (int) ($row['total'] ?? 0)));
        $dailyRows = $daily->map(fn ($row) => [
            $row['date'] ?? '',
            (int) ($row['total'] ?? 0),
            round(((int) ($row['total'] ?? 0) / $dailyMax) * 100, 1),
        ])->all();

        $typeRows = collect($dashboard['by_event_type'] ?? []);
        $typeTotal = max(1, (int) $typeRows->sum(fn ($row) => (int) ($row['total'] ?? 0)));
        $types = $typeRows->map(fn ($row) => [
            $row['event_type'] ?? '',
            (int) ($row['total'] ?? 0),
            round(((int) ($row['total'] ?? 0) / $typeTotal) * 100, 1),
        ])->all();

        $weekdayRows = collect($dashboard['weekday_counts'] ?? [])->map(fn ($row) => [
            $row['label'] ?? '',
            (int) ($row['total'] ?? 0),
        ])->all();

        $deviceTotal = max(1, (int) ($devices['total'] ?? 0));
        $deviceRows = [
            ['Android', (int) ($devices['android'] ?? 0), round(((int) ($devices['android'] ?? 0) / $deviceTotal) * 100, 1)],
            ['iOS', (int) ($devices['ios'] ?? 0), round(((int) ($devices['ios'] ?? 0) / $deviceTotal) * 100, 1)],
            ['Web', (int) ($devices['web'] ?? 0), round(((int) ($devices['web'] ?? 0) / $deviceTotal) * 100, 1)],
            ['Other', (int) ($devices['other'] ?? 0), round(((int) ($devices['other'] ?? 0) / $deviceTotal) * 100, 1)],
        ];

        $hourlyVisits = collect($hourlyByMetric['visits'] ?? $dashboard['hourly_counts'] ?? [])->map(fn ($row) => [
            sprintf('%02d:00', (int) ($row['hour'] ?? 0)),
            (int) ($row['total'] ?? 0),
        ])->all();
        $hourlyApp = collect($hourlyByMetric['app_usage'] ?? [])->map(fn ($row) => [
            sprintf('%02d:00', (int) ($row['hour'] ?? 0)),
            (int) ($row['total'] ?? 0),
        ])->all();
        $hourlyViews = collect($hourlyByMetric['content_views'] ?? [])->map(fn ($row) => [
            sprintf('%02d:00', (int) ($row['hour'] ?? 0)),
            (int) ($row['total'] ?? 0),
        ])->all();
        $hourlyEngagement = collect($hourlyByMetric['engagements'] ?? [])->map(fn ($row) => [
            sprintf('%02d:00', (int) ($row['hour'] ?? 0)),
            (int) ($row['total'] ?? 0),
        ])->all();

        $funnelRows = [
            ['Views', (int) ($funnel['views'] ?? 0), 100, 0],
            ['Bookmarks', (int) ($funnel['bookmarks'] ?? 0), (int) ($funnel['views'] ?? 0) > 0 ? round(((int) ($funnel['bookmarks'] ?? 0) / (int) $funnel['views']) * 100, 1) : 0, '—'],
            ['Shares', (int) ($funnel['shares'] ?? 0), (int) ($funnel['views'] ?? 0) > 0 ? round(((int) ($funnel['shares'] ?? 0) / (int) $funnel['views']) * 100, 1) : 0, '—'],
            ['Engagement rate %', $funnel['engagement'] ?? 0, '—', $funnel['formula'] ?? ''],
        ];

        $insightRows = collect($dashboard['insights'] ?? [])->map(fn ($row) => [
            is_array($row) ? ($row['tone'] ?? 'insight') : 'insight',
            is_array($row) ? ($row['text'] ?? ($row['body'] ?? ($row['message'] ?? json_encode($row)))) : (string) $row,
        ])->all();

        return [
            [
                'name' => 'KPIs',
                'headers' => ['Metric', 'Current', 'Previous / note', 'Definition'],
                'rows' => $kpiRows,
            ],
            [
                'name' => 'Daily activity',
                'headers' => ['Date', 'Events', 'Share of peak %'],
                'rows' => $dailyRows,
                'chart' => ['title' => 'Daily activity', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly visits',
                'headers' => ['Hour', 'Events'],
                'rows' => $hourlyVisits,
                'chart' => ['title' => 'Hourly visits', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly app usage',
                'headers' => ['Hour', 'Events'],
                'rows' => $hourlyApp,
                'chart' => ['title' => 'Hourly app usage', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly content views',
                'headers' => ['Hour', 'Events'],
                'rows' => $hourlyViews,
                'chart' => ['title' => 'Hourly content views', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly engagement',
                'headers' => ['Hour', 'Events'],
                'rows' => $hourlyEngagement,
                'chart' => ['title' => 'Hourly engagement', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Weekday',
                'headers' => ['Weekday', 'Events'],
                'rows' => $weekdayRows,
                'chart' => ['title' => 'Weekday performance', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Event types',
                'headers' => ['Event type', 'Total', 'Share %'],
                'rows' => $types,
                'chart' => ['title' => 'Events by type', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Devices',
                'headers' => ['Platform', 'Events', 'Share %'],
                'rows' => $deviceRows,
                'chart' => ['title' => 'Device mix', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Funnel',
                'headers' => ['Step', 'Count', 'Conversion %', 'Notes'],
                'rows' => $funnelRows,
            ],
            [
                'name' => 'Insights',
                'headers' => ['Tone', 'Detail'],
                'rows' => $insightRows,
            ],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, value: float|int}>}>
     */
    private function summaryCharts(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $daily = collect($dashboard['daily_counts'] ?? [])
            ->map(fn ($row) => [
                'label' => (string) ($row['date'] ?? ''),
                'value' => (int) ($row['total'] ?? 0),
            ])->all();
        $types = collect($dashboard['by_event_type'] ?? [])
            ->take(8)
            ->map(fn ($row) => [
                'label' => (string) ($row['event_type'] ?? ''),
                'value' => (int) ($row['total'] ?? 0),
            ])->all();
        $hourly = collect($dashboard['hourly_by_metric']['series']['visits'] ?? $dashboard['hourly_counts'] ?? [])
            ->map(fn ($row) => [
                'label' => sprintf('%02d', (int) ($row['hour'] ?? 0)),
                'value' => (int) ($row['total'] ?? 0),
            ])->all();
        $weekday = collect($dashboard['weekday_counts'] ?? [])
            ->map(fn ($row) => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (int) ($row['total'] ?? 0),
            ])->all();
        $devices = $dashboard['devices'] ?? [];

        return array_values(array_filter([
            ['title' => 'Daily activity', 'items' => $daily],
            ['title' => 'Hourly visits', 'items' => $hourly],
            ['title' => 'Weekday performance', 'items' => $weekday],
            ['title' => 'Top event types', 'items' => $types],
            ['title' => 'Device mix', 'items' => [
                ['label' => 'Android', 'value' => (int) ($devices['android'] ?? 0)],
                ['label' => 'iOS', 'value' => (int) ($devices['ios'] ?? 0)],
                ['label' => 'Web', 'value' => (int) ($devices['web'] ?? 0)],
                ['label' => 'Other', 'value' => (int) ($devices['other'] ?? 0)],
            ]],
        ], fn ($chart) => collect($chart['items'])->sum('value') > 0));
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}>
     */
    private function demographicsSheets(AnalyticsDateRange $range): array
    {
        $overview = $this->analyticsRepository->geographicOverview($range, 100);
        $summary = $overview['summary'] ?? [];

        $mapLevel = function (array $block) {
            return collect($block['rows'] ?? [])->map(function ($row) {
                $registered = (int) ($row['registered'] ?? 0);
                $active = (int) ($row['active'] ?? 0);

                return [
                    $row['location'] ?? '',
                    $registered,
                    $active,
                    $registered > 0 ? round(($active / $registered) * 100, 1) : 0,
                ];
            })->all();
        };

        return [
            [
                'name' => 'Coverage',
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['Registered users', $summary['registered_users'] ?? 0],
                    ['With profile location', $summary['with_location'] ?? 0],
                    ['Missing location', $summary['missing_location'] ?? 0],
                    ['Active in range', $summary['active_in_range'] ?? 0],
                    ['Location coverage %', $summary['location_coverage_pct'] ?? 0],
                ],
            ],
            [
                'name' => 'Region',
                'headers' => ['Region', 'Registered', 'Active', 'Active %'],
                'rows' => $mapLevel($overview['region'] ?? []),
                'chart' => ['title' => 'Users by region', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Province',
                'headers' => ['Province', 'Registered', 'Active', 'Active %'],
                'rows' => $mapLevel($overview['province'] ?? []),
                'chart' => ['title' => 'Users by province', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'City Municipality',
                'headers' => ['City / Municipality', 'Registered', 'Active', 'Active %'],
                'rows' => $mapLevel($overview['city'] ?? []),
                'chart' => ['title' => 'Users by city', 'category_col' => 1, 'value_col' => 2],
            ],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, value: float|int}>}>
     */
    private function demographicsCharts(AnalyticsDateRange $range): array
    {
        $overview = $this->analyticsRepository->geographicOverview($range, 12);
        $regionItems = collect($overview['region']['rows'] ?? [])
            ->take(10)
            ->map(fn ($row) => [
                'label' => (string) ($row['location'] ?? ''),
                'value' => (int) ($row['registered'] ?? 0),
            ])->all();

        return array_values(array_filter([
            ['title' => 'Top regions (registered)', 'items' => $regionItems],
        ], fn ($chart) => collect($chart['items'])->sum('value') > 0));
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function usersSheet(AnalyticsDateRange $range): array
    {
        $page = $this->analyticsRepository->filterUsers([], $range, 5000);
        $rows = collect($page['data'] ?? [])->map(fn ($row) => [
            $row['id'] ?? '',
            $row['name'] ?? '',
            $row['email'] ?? '',
            $row['age'] ?? '',
            $row['region'] ?? '',
            $row['province'] ?? '',
            $row['city'] ?? '',
            $row['registered_at'] ?? '',
            $row['events_in_range'] ?? 0,
            $row['last_active_at'] ?? '',
        ])->all();

        return [
            'name' => 'Users',
            'headers' => [
                'ID', 'Name', 'Email', 'Age', 'Region', 'Province', 'City',
                'Registered at', 'Events in range', 'Last active at',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}
     */
    private function leaderboardSheet(AnalyticsDateRange $range): array
    {
        $payload = $this->analyticsRepository->leaderboard($range, 'engagements', 100);
        $rows = collect($payload['rows'] ?? [])->map(fn ($row) => [
            $row['rank'] ?? '',
            $row['user_id'] ?? '',
            $row['name'] ?? '',
            $row['subtitle'] ?? '',
            $row['city'] ?? '',
            $row['province'] ?? '',
            $row['visits'] ?? 0,
            $row['app_usage'] ?? 0,
            $row['views'] ?? 0,
            $row['likes'] ?? 0,
            $row['comments'] ?? 0,
            $row['shares'] ?? 0,
            $row['bookmarks'] ?? 0,
            $row['engagements'] ?? 0,
            $row['app_opens'] ?? 0,
            $row['sessions'] ?? 0,
            $row['duration_seconds'] ?? 0,
            $row['engagement_score'] ?? 0,
            $row['metric_value'] ?? 0,
        ])->all();

        return [
            'name' => 'Leaderboard',
            'headers' => [
                'Rank', 'User ID', 'Name', 'Subtitle', 'City', 'Province',
                'Visits', 'App usage', 'Content views', 'Likes', 'Comments', 'Shares', 'Bookmarks',
                'Engagement', 'App opens', 'Sessions', 'Duration (s)', 'Engagement score', 'Metric value',
            ],
            'rows' => $rows,
            'chart' => ['title' => 'Top users by engagement', 'category_col' => 3, 'value_col' => 14, 'max_points' => 10],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, value: float|int}>}>
     */
    private function leaderboardCharts(AnalyticsDateRange $range): array
    {
        $payload = $this->analyticsRepository->leaderboard($range, 'engagements', 10);
        $items = collect($payload['rows'] ?? [])
            ->take(10)
            ->map(fn ($row) => [
                'label' => (string) ($row['name'] ?? ('User #'.($row['user_id'] ?? ''))),
                'value' => (int) ($row['engagements'] ?? 0),
            ])->all();

        return array_values(array_filter([
            ['title' => 'Top 10 users by engagement', 'items' => $items],
        ], fn ($chart) => collect($chart['items'])->sum('value') > 0));
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function retentionSheet(AnalyticsDateRange $range): array
    {
        $payload = $this->analyticsRepository->retentionCohorts($range);
        $offsets = $payload['offsets'] ?? [1, 7, 14, 30];
        $headers = array_merge(['Cohort', 'Size'], array_map(fn ($d) => 'D'.$d.' %', $offsets));
        $rows = collect($payload['rows'] ?? [])->map(function ($row) use ($offsets) {
            $cells = [$row['cohort'] ?? '', $row['size'] ?? 0];
            foreach ($offsets as $offset) {
                $cells[] = $row['retention'][strval($offset)] ?? $row['retention'][$offset] ?? '';
            }

            return $cells;
        })->all();

        return [
            'name' => 'Retention',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}>
     */
    private function mobileSheets(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $devices = $dashboard['devices'] ?? [];
        $deviceTotal = max(1, (int) ($devices['total'] ?? 0));
        $screens = collect($dashboard['screen_breakdown'] ?? [])->map(fn ($row) => [
            $row['screen_name'] ?? ($row['name'] ?? ''),
            $row['total'] ?? ($row['count'] ?? 0),
        ])->all();
        $hourlyApp = collect($dashboard['hourly_by_metric']['series']['app_usage'] ?? [])->map(fn ($row) => [
            sprintf('%02d:00', (int) ($row['hour'] ?? 0)),
            (int) ($row['total'] ?? 0),
        ])->all();

        return [
            [
                'name' => 'Devices',
                'headers' => ['Platform', 'Events', 'Share %'],
                'rows' => [
                    ['Android', (int) ($devices['android'] ?? 0), round(((int) ($devices['android'] ?? 0) / $deviceTotal) * 100, 1)],
                    ['iOS', (int) ($devices['ios'] ?? 0), round(((int) ($devices['ios'] ?? 0) / $deviceTotal) * 100, 1)],
                    ['Web', (int) ($devices['web'] ?? 0), round(((int) ($devices['web'] ?? 0) / $deviceTotal) * 100, 1)],
                    ['Other', (int) ($devices['other'] ?? 0), round(((int) ($devices['other'] ?? 0) / $deviceTotal) * 100, 1)],
                    ['Total', (int) ($devices['total'] ?? 0), 100],
                ],
                'chart' => ['title' => 'Device mix', 'category_col' => 1, 'value_col' => 2, 'max_points' => 4],
            ],
            [
                'name' => 'Screens',
                'headers' => ['Screen', 'Events'],
                'rows' => $screens,
                'chart' => ['title' => 'Top screens', 'category_col' => 1, 'value_col' => 2, 'max_points' => 12],
            ],
            [
                'name' => 'Hourly app usage',
                'headers' => ['Hour', 'Events'],
                'rows' => $hourlyApp,
                'chart' => ['title' => 'Hourly app usage', 'category_col' => 1, 'value_col' => 2],
            ],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, value: float|int}>}>
     */
    private function mobileCharts(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $devices = $dashboard['devices'] ?? [];
        $screens = collect($dashboard['screen_breakdown'] ?? [])
            ->take(8)
            ->map(fn ($row) => [
                'label' => (string) ($row['screen_name'] ?? ($row['name'] ?? 'Screen')),
                'value' => (int) ($row['total'] ?? ($row['count'] ?? 0)),
            ])->all();

        return array_values(array_filter([
            ['title' => 'Device mix', 'items' => [
                ['label' => 'Android', 'value' => (int) ($devices['android'] ?? 0)],
                ['label' => 'iOS', 'value' => (int) ($devices['ios'] ?? 0)],
                ['label' => 'Web', 'value' => (int) ($devices['web'] ?? 0)],
                ['label' => 'Other', 'value' => (int) ($devices['other'] ?? 0)],
            ]],
            ['title' => 'Top screens', 'items' => $screens],
        ], fn ($chart) => collect($chart['items'])->sum('value') > 0));
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}>
     */
    private function contentSheets(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $topPosts = collect($dashboard['top_posts'] ?? [])->map(function ($row) {
            $data = is_array($row) ? $row : (array) $row;
            $post = $data['post'] ?? null;
            if (is_object($post)) {
                $post = [
                    'id' => $post->id ?? null,
                    'title' => $post->title ?? '',
                    'category' => $post->category->name ?? '',
                    'reviews_count' => $post->reviews_count ?? 0,
                    'reviews_avg_rating' => isset($post->reviews_avg_rating)
                        ? round((float) $post->reviews_avg_rating, 2)
                        : '',
                ];
            } elseif (! is_array($post)) {
                $post = [];
            }

            return [
                $post['id'] ?? ($data['post_id'] ?? ''),
                $post['title'] ?? ($data['title'] ?? ''),
                is_array($post['category'] ?? null)
                    ? ($post['category']['name'] ?? '')
                    : ($post['category'] ?? ''),
                (int) ($data['views'] ?? 0),
                (int) ($data['previous_views'] ?? 0),
                (int) ($post['reviews_count'] ?? 0),
                $post['reviews_avg_rating'] ?? '',
            ];
        })->all();

        $rated = collect($dashboard['top_rated_posts'] ?? [])->map(function ($row) {
            $data = is_array($row) ? $row : (array) $row;
            $post = $data['post'] ?? [];
            if (is_object($post)) {
                $post = ['id' => $post->id ?? null, 'title' => $post->title ?? ''];
            }

            return [
                is_array($post) ? ($post['id'] ?? ($data['id'] ?? '')) : ($data['id'] ?? ''),
                is_array($post) ? ($post['title'] ?? ($data['title'] ?? '')) : ($data['title'] ?? ''),
                $data['average_rating'] ?? ($data['avg_rating'] ?? ''),
                $data['reviews_count'] ?? ($data['total_reviews'] ?? 0),
            ];
        })->all();

        $categories = collect($dashboard['top_categories'] ?? [])->map(function ($row) {
            $data = is_array($row) ? $row : (array) $row;

            return [
                $data['name'] ?? '',
                (int) ($data['views'] ?? 0),
            ];
        })->all();

        $ratings = collect($dashboard['rating_distribution'] ?? [])->map(function ($row) {
            $data = is_array($row) ? $row : (array) $row;

            return [
                ($data['rating'] ?? '').' star',
                (int) ($data['total'] ?? 0),
            ];
        })->all();

        return [
            [
                'name' => 'Top posts',
                'headers' => [
                    'Post ID', 'Title', 'Category', 'Views', 'Previous views', 'Reviews', 'Avg rating',
                ],
                'rows' => $topPosts,
                'chart' => ['title' => 'Top posts by views', 'category_col' => 2, 'value_col' => 4, 'max_points' => 10],
            ],
            [
                'name' => 'Top rated posts',
                'headers' => ['Post ID', 'Title', 'Avg rating', 'Reviews'],
                'rows' => $rated,
            ],
            [
                'name' => 'Top categories',
                'headers' => ['Category', 'Views'],
                'rows' => $categories,
                'chart' => ['title' => 'Top categories', 'category_col' => 1, 'value_col' => 2, 'max_points' => 10],
            ],
            [
                'name' => 'Rating distribution',
                'headers' => ['Rating', 'Count'],
                'rows' => $ratings,
                'chart' => ['title' => 'Rating distribution', 'category_col' => 1, 'value_col' => 2],
            ],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, value: float|int}>}>
     */
    private function contentCharts(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $posts = collect($dashboard['top_posts'] ?? [])
            ->take(8)
            ->map(function ($row) {
                $title = $row['post']['title'] ?? ($row['title'] ?? ('Post #'.($row['post_id'] ?? '')));

                return [
                    'label' => mb_strimwidth((string) $title, 0, 28, '…'),
                    'value' => (int) ($row['views'] ?? 0),
                ];
            })->all();
        $categories = collect($dashboard['top_categories'] ?? [])
            ->take(8)
            ->map(fn ($row) => [
                'label' => (string) ($row['name'] ?? 'Category'),
                'value' => (int) ($row['views'] ?? 0),
            ])->all();

        return array_values(array_filter([
            ['title' => 'Top posts by views', 'items' => $posts],
            ['title' => 'Top categories', 'items' => $categories],
        ], fn ($chart) => collect($chart['items'])->sum('value') > 0));
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}
     */
    private function journeysSheet(AnalyticsDateRange $range): array
    {
        $payload = $this->analyticsRepository->funnelReport('default_content', $range);
        $steps = $payload['steps'] ?? ($payload['report']['steps'] ?? []);
        $rows = collect($steps)->map(fn ($step) => [
            $step['name'] ?? '',
            $step['users'] ?? 0,
            $step['conversion_from_previous'] ?? 0,
            $step['drop_off'] ?? 0,
        ])->all();

        return [
            'name' => 'Funnel',
            'headers' => ['Step', 'Users', 'Conversion %', 'Drop-off'],
            'rows' => $rows,
            'chart' => ['title' => 'Funnel users by step', 'category_col' => 1, 'value_col' => 2],
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{label: string, value: float|int}>}>
     */
    private function journeysCharts(AnalyticsDateRange $range): array
    {
        $payload = $this->analyticsRepository->funnelReport('default_content', $range);
        $steps = $payload['steps'] ?? ($payload['report']['steps'] ?? []);
        $items = collect($steps)->map(fn ($step) => [
            'label' => (string) ($step['name'] ?? 'Step'),
            'value' => (int) ($step['users'] ?? 0),
        ])->all();

        return array_values(array_filter([
            ['title' => 'Journey funnel', 'items' => $items],
        ], fn ($chart) => collect($chart['items'])->sum('value') > 0));
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function alertsSheet(): array
    {
        $rows = \App\Models\AnalyticsAnomalyAlert::query()
            ->orderByDesc('detected_at')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => [
                $row->id,
                $row->title,
                $row->metric_key,
                $row->previous_value,
                $row->current_value,
                $row->percent_change,
                $row->severity,
                $row->status,
                optional($row->detected_at)?->toIso8601String(),
            ])->all();

        return [
            'name' => 'Alerts',
            'headers' => [
                'ID', 'Title', 'Metric', 'Previous', 'Current', 'Change %', 'Severity', 'Status', 'Detected at',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function eventsSheet(AnalyticsDateRange $range): array
    {
        $rows = $this->analyticsRepository->allForExport($range)->map(fn ($row) => [
            $row->id,
            $row->event_type,
            $row->post_id ?? '',
            $row->post->title ?? '',
            $row->user_id ?? '',
            $row->user->email ?? '',
            $row->platform ?? '',
            $row->session_id ?? '',
            $row->screen_name ?? '',
            $row->resource_type ?? '',
            $row->resource_id ?? '',
            optional($row->occurred_at)?->toIso8601String() ?? '',
            optional($row->created_at)?->toIso8601String() ?? '',
        ])->all();

        return [
            'name' => 'Events',
            'headers' => [
                'ID', 'Event type', 'Post ID', 'Post title', 'User ID', 'User email',
                'Platform', 'Session ID', 'Screen', 'Resource type', 'Resource ID', 'Occurred at', 'Created at',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{title: string, subtitle: string, sheets: list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>}  $dataset
     */
    private function toCsv(array $dataset): string
    {
        $lines = [
            $this->csvRow([$dataset['title']]),
            $this->csvRow([$dataset['subtitle']]),
            $this->csvRow(['Dangerous Drugs Board · DAPE-MA Analytics']),
            $this->csvRow(['Generated', now()->toDateTimeString()]),
            '',
        ];

        foreach ($dataset['sheets'] as $index => $sheet) {
            if ($index > 0) {
                $lines[] = '';
            }
            $lines[] = $this->csvRow(['Section', $sheet['name']]);
            $lines[] = $this->csvRow($sheet['headers']);
            foreach ($sheet['rows'] as $row) {
                $lines[] = $this->csvRow($row);
            }
        }

        return "\u{FEFF}".implode("\n", $lines);
    }

    /**
     * @param  array{title: string, subtitle: string, sheets: list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>}  $dataset
     */
    private function toExcel(array $dataset): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('DAPE-MA')
            ->setCompany('Dangerous Drugs Board')
            ->setTitle($dataset['title'])
            ->setSubject($dataset['subtitle']);

        foreach ($dataset['sheets'] as $index => $sheet) {
            $worksheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $worksheet->setTitle($this->safeSheetName($sheet['name']));

            $colCount = max(count($sheet['headers']), 1);
            $lastCol = Coordinate::stringFromColumnIndex($colCount);

            $worksheet->mergeCells('A1:'.$lastCol.'1');
            $worksheet->setCellValue('A1', $dataset['title']);
            $worksheet->getStyle('A1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => 'FFFFFF'],
                    'name' => 'Calibri',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::BRAND_NAVY],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);
            $worksheet->getRowDimension(1)->setRowHeight(28);

            $worksheet->mergeCells('A2:'.$lastCol.'2');
            $worksheet->setCellValue('A2', $dataset['subtitle'].' · '.$sheet['name']);
            $worksheet->getStyle('A2')->applyFromArray([
                'font' => [
                    'size' => 10,
                    'color' => ['rgb' => self::BRAND_NAVY],
                    'name' => 'Calibri',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::BRAND_GOLD],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $worksheet->getRowDimension(2)->setRowHeight(20);

            $headerRow = 4;
            foreach ($sheet['headers'] as $colIndex => $header) {
                $cell = Coordinate::stringFromColumnIndex($colIndex + 1).$headerRow;
                $worksheet->setCellValue($cell, $header);
            }
            $worksheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 10,
                    'name' => 'Calibri',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::BRAND_BLUE],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => self::BRAND_GOLD],
                    ],
                ],
            ]);
            $worksheet->getRowDimension($headerRow)->setRowHeight(22);

            $rowNumber = $headerRow + 1;
            foreach ($sheet['rows'] as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $cell = Coordinate::stringFromColumnIndex($colIndex + 1).$rowNumber;
                    $worksheet->setCellValue($cell, $value);
                }

                $rowStyle = [
                    'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '1E293B']],
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E2E8F0'],
                        ],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ];
                if ($rowIndex % 2 === 1) {
                    $rowStyle['fill'] = [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => self::BRAND_SLATE],
                    ];
                }
                $worksheet->getStyle('A'.$rowNumber.':'.$lastCol.$rowNumber)->applyFromArray($rowStyle);
                $rowNumber++;
            }

            for ($col = 1; $col <= $colCount; $col++) {
                $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
            }

            $worksheet->freezePane('A5');
            $worksheet->setAutoFilter('A'.$headerRow.':'.$lastCol.$headerRow);

            if (! empty($sheet['chart']) && count($sheet['rows']) > 0) {
                $this->attachSheetChart(
                    $worksheet,
                    $this->safeSheetName($sheet['name']),
                    $headerRow,
                    count($sheet['rows']),
                    $sheet['chart'],
                    $colCount,
                );
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $temp = fopen('php://temp', 'r+');
        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->save($temp);
        rewind($temp);
        $body = stream_get_contents($temp) ?: '';
        fclose($temp);
        $spreadsheet->disconnectWorksheets();

        return $body;
    }

    /**
     * @param  array{title?: string, category_col?: int, value_col?: int, max_points?: int}  $chartConfig
     */
    private function attachSheetChart(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,
        string $sheetName,
        int $headerRow,
        int $rowCount,
        array $chartConfig,
        int $colCount,
    ): void {
        $categoryCol = max(1, (int) ($chartConfig['category_col'] ?? 1));
        $valueCol = max(1, (int) ($chartConfig['value_col'] ?? 2));
        $maxPoints = max(1, (int) ($chartConfig['max_points'] ?? min($rowCount, 24)));
        $points = min($rowCount, $maxPoints);
        if ($points < 1) {
            return;
        }

        $startRow = $headerRow + 1;
        $endRow = $headerRow + $points;
        $safeName = str_replace("'", "''", $sheetName);
        $catColLetter = Coordinate::stringFromColumnIndex($categoryCol);
        $valColLetter = Coordinate::stringFromColumnIndex($valueCol);
        $labelColLetter = Coordinate::stringFromColumnIndex($valueCol);

        $dataSeriesLabels = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'{$safeName}'!\${$labelColLetter}\${$headerRow}",
                null,
                1,
            ),
        ];
        $xAxisTickValues = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'{$safeName}'!\${$catColLetter}\${$startRow}:\${$catColLetter}\${$endRow}",
                null,
                $points,
            ),
        ];
        $dataSeriesValues = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'{$safeName}'!\${$valColLetter}\${$startRow}:\${$valColLetter}\${$endRow}",
                null,
                $points,
            ),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues,
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend = new ChartLegend(ChartLegend::POSITION_RIGHT, null, false);
        $chart = new Chart(
            'chart_'.preg_replace('/\W+/', '_', strtolower($sheetName)),
            new ChartTitle((string) ($chartConfig['title'] ?? $sheetName)),
            $legend,
            $plotArea,
        );

        $anchorCol = Coordinate::stringFromColumnIndex(max($colCount + 2, 4));
        $chart->setTopLeftPosition($anchorCol.'4');
        $chart->setBottomRightPosition(
            Coordinate::stringFromColumnIndex(max($colCount + 10, 12)).'20'
        );
        $worksheet->addChart($chart);
    }

    /**
     * @param  array{title: string, subtitle: string, sheets: list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>, charts?: list<array{title: string, items: list<array{label: string, value: float|int}>}>}  $dataset
     */
    private function toPdf(array $dataset): string
    {
        $html = view('exports.analytics-report', [
            'title' => $dataset['title'],
            'subtitle' => $dataset['subtitle'],
            'sheets' => $dataset['sheets'],
            'charts' => $dataset['charts'] ?? [],
            'generatedAt' => now()->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'sealSrc' => $this->sealDataUri(),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([
            base_path(),
            public_path(),
            storage_path(),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output() ?? '';
    }

    private function sealDataUri(): ?string
    {
        $candidates = [
            public_path('email/ddb-seal.png'),
            public_path('images/ddb-seal.png'),
            public_path('img/ddb-seal.png'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $binary = file_get_contents($path);
            if ($binary === false || $binary === '') {
                continue;
            }

            return 'data:image/png;base64,'.base64_encode($binary);
        }

        return null;
    }

    private function csvRow(array $columns): string
    {
        return implode(',', array_map(
            fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
            $columns,
        ));
    }

    private function safeSheetName(string $name): string
    {
        $clean = preg_replace('/[\\\\\/?*\\[\\]:]/', ' ', $name) ?: 'Sheet';

        return mb_substr(trim($clean), 0, 31) ?: 'Sheet';
    }
}
