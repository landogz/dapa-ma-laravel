<?php

namespace App\Services\Analytics;

use App\Repositories\AnalyticsRepository;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsEventTypes;
use App\Support\Analytics\AnalyticsExportDate;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class AnalyticsReportExporter
{
    private const BRAND_NAVY = '123A60';

    private const BRAND_BLUE = '055498';

    private const BRAND_GOLD = 'FBD116';

    private const BRAND_SLATE = 'F8FAFC';

    private const PDF_ROW_LIMIT = 400;

    private const TABULAR_ROW_LIMIT = 10000;

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
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        $format = in_array($format, ['csv', 'xlsx', 'pdf'], true) ? $format : 'csv';
        $reportType = $this->normalizeReportType($reportType);
        $dataset = $this->dataset($reportType, $range, $format);
        $dataset = $this->formatDatasetCells($dataset);
        if ($format === 'pdf') {
            $dataset = $this->limitPdfDataset($dataset);
        }

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
    private function dataset(string $reportType, AnalyticsDateRange $range, string $format = 'csv'): array
    {
        $subtitle = 'Detailed report · Range: '.$range->label
            .' · '.AnalyticsExportDate::date($range->from).' to '.AnalyticsExportDate::date($range->to)
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
                'sheets' => [$this->usersSheet($range, $format)],
                'charts' => [],
            ],
            'leaderboard' => [
                'title' => 'DAPE-MA Analytics — Top users leaderboard (detailed)',
                'subtitle' => $subtitle,
                'sheets' => [$this->leaderboardSheet($range, $format)],
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
                    [$this->usersSheet($range, $format)],
                    [$this->leaderboardSheet($range, $format)],
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
                'sheets' => [$this->eventsSheet($range, $format)],
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
        $careTotals = $dashboard['care_tools']['totals'] ?? [];
        $views = (int) ($funnel['views'] ?? 0);

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
            ['Downloads', $current['downloads'] ?? ($funnel['downloads'] ?? ($kpis['downloads'] ?? 0)), $previous['downloads'] ?? '—', 'IEC material downloads'],
            ['Searches', $current['searches'] ?? 0, $previous['searches'] ?? 0, 'Search events'],
            ['Journal saves', $careTotals['journal_creates'] ?? 0, '—', 'Count only'],
            ['Care checks', $careTotals['care_checks'] ?? 0, '—', 'Count only'],
            ['Mood check-ins', $careTotals['mood_checkins'] ?? 0, '—', 'Count only'],
            ['Calm sessions', $careTotals['calm_sessions'] ?? 0, '—', 'Count only'],
            ['Color Fun opens', $careTotals['color_fun_opens'] ?? 0, '—', 'Count only'],
            ['Color Fun completes', $careTotals['color_fun_completes'] ?? 0, '—', 'Count only'],
            ['Notification opens', $careTotals['notification_opens'] ?? 0, '—', 'Count only'],
            ['Contest submissions', $careTotals['contest_submits'] ?? 0, '—', 'Count only'],
            ['Engagement rate %', $funnel['engagement'] ?? 0, '—', '(bookmarks + shares) / views'],
            ['Relational engagements', $funnel['relational_engagements'] ?? ($kpis['relational_engagements'] ?? 0), '—', 'Likes/comments/bookmarks/shares'],
            ['Avg events / day', $audience['avg_per_day'] ?? 0, '—', 'Volume / day span'],
            ['Peak day', AnalyticsExportDate::auto($audience['peak_day']['date'] ?? '—'), $audience['peak_day']['total'] ?? 0, 'Busiest calendar day'],
            ['Comparison window', $periodLabel, '—', 'Previous period label'],
        ];

        $daily = collect($dashboard['daily_counts'] ?? [])->values();
        $dailyMax = max(1, (int) $daily->max(fn ($row) => (int) ($row['total'] ?? 0)));
        $dailyRows = $daily->map(fn ($row) => [
            AnalyticsExportDate::date($row['date'] ?? ''),
            (int) ($row['total'] ?? 0),
            round(((int) ($row['total'] ?? 0) / $dailyMax) * 100, 1),
        ])->all();

        $typeRows = collect($dashboard['by_event_type'] ?? [])
            ->groupBy(fn ($row) => $this->humanizeEventType((string) ($row['event_type'] ?? '')))
            ->map(fn ($rows, $label) => [
                'label' => $label,
                'total' => (int) $rows->sum(fn ($row) => (int) ($row['total'] ?? 0)),
            ])
            ->sortByDesc('total')
            ->values();
        $typeTotal = max(1, (int) $typeRows->sum('total'));
        $types = $typeRows->map(fn ($row) => [
            $row['label'],
            (int) $row['total'],
            round(((int) $row['total'] / $typeTotal) * 100, 1),
        ])->all();

        $weekdayRows = $this->rowsWithPeakShare(
            collect($dashboard['weekday_counts'] ?? [])->map(fn ($row) => [
                (string) ($row['label'] ?? ''),
                (int) ($row['total'] ?? 0),
            ])->all(),
        );

        $deviceTotal = max(1, (int) ($devices['total'] ?? 0));
        $deviceRows = [
            ['Android', (int) ($devices['android'] ?? 0), round(((int) ($devices['android'] ?? 0) / $deviceTotal) * 100, 1)],
            ['iOS', (int) ($devices['ios'] ?? 0), round(((int) ($devices['ios'] ?? 0) / $deviceTotal) * 100, 1)],
            ['Web', (int) ($devices['web'] ?? 0), round(((int) ($devices['web'] ?? 0) / $deviceTotal) * 100, 1)],
            ['Other', (int) ($devices['other'] ?? 0), round(((int) ($devices['other'] ?? 0) / $deviceTotal) * 100, 1)],
        ];

        $hourlyVisits = $this->hourlyRows($hourlyByMetric['visits'] ?? $dashboard['hourly_counts'] ?? []);
        $hourlyApp = $this->hourlyRows($hourlyByMetric['app_usage'] ?? []);
        $hourlyViews = $this->hourlyRows($hourlyByMetric['content_views'] ?? []);
        $hourlyEngagement = $this->hourlyRows($hourlyByMetric['engagements'] ?? []);

        $funnelRows = [
            ['Views', $views, 100, 0],
            ['Bookmarks', (int) ($funnel['bookmarks'] ?? 0), $views > 0 ? round(((int) ($funnel['bookmarks'] ?? 0) / $views) * 100, 1) : 0, '—'],
            ['Shares', (int) ($funnel['shares'] ?? 0), $views > 0 ? round(((int) ($funnel['shares'] ?? 0) / $views) * 100, 1) : 0, '—'],
            ['Downloads', (int) ($funnel['downloads'] ?? 0), $views > 0 ? round(((int) ($funnel['downloads'] ?? 0) / $views) * 100, 1) : 0, 'IEC material downloads'],
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
                'headers' => ['Date', 'Events', '% of peak'],
                'rows' => $dailyRows,
                'chart' => ['title' => 'Daily activity', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly visits',
                'headers' => ['Hour', 'Events', '% of peak'],
                'rows' => $hourlyVisits,
                'chart' => ['title' => 'Hourly visits', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly app usage',
                'headers' => ['Hour', 'Events', '% of peak'],
                'rows' => $hourlyApp,
                'chart' => ['title' => 'Hourly app usage', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly content views',
                'headers' => ['Hour', 'Events', '% of peak'],
                'rows' => $hourlyViews,
                'chart' => ['title' => 'Hourly content views', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Hourly engagement',
                'headers' => ['Hour', 'Events', '% of peak'],
                'rows' => $hourlyEngagement,
                'chart' => ['title' => 'Hourly engagement', 'category_col' => 1, 'value_col' => 2],
            ],
            [
                'name' => 'Weekday',
                'headers' => ['Weekday', 'Events', '% of peak'],
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
            $this->careToolsSheet($dashboard),
            ...$this->contentCatalogSheets($dashboard),
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
                'label' => AnalyticsExportDate::date($row['date'] ?? ''),
                'value' => (int) ($row['total'] ?? 0),
            ])->all();
        $types = collect($dashboard['by_event_type'] ?? [])
            ->groupBy(fn ($row) => $this->humanizeEventType((string) ($row['event_type'] ?? '')))
            ->map(fn ($rows, $label) => [
                'label' => $label,
                'value' => (int) $rows->sum(fn ($row) => (int) ($row['total'] ?? 0)),
            ])
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->all();
        $hourly = collect($dashboard['hourly_by_metric']['series']['visits'] ?? $dashboard['hourly_counts'] ?? [])
            ->filter(fn ($row) => (int) ($row['total'] ?? 0) > 0)
            ->map(fn ($row) => [
                'label' => AnalyticsExportDate::hour($row['hour'] ?? 0),
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
    private function usersSheet(AnalyticsDateRange $range, string $format = 'csv'): array
    {
        $compact = $format === 'pdf';
        $limit = $compact ? self::PDF_ROW_LIMIT : 5000;
        $page = $this->analyticsRepository->filterUsers([], $range, $limit);
        $rows = collect($page['data'] ?? [])->map(function ($row) use ($compact) {
            if ($compact) {
                return [
                    $row['name'] ?? '',
                    $row['email'] ?? '',
                    $this->formatLocation($row),
                    AnalyticsExportDate::dateTime($row['registered_at'] ?? ''),
                    (int) ($row['events_in_range'] ?? 0),
                    AnalyticsExportDate::dateTime($row['last_active_at'] ?? ''),
                ];
            }

            return [
                $row['id'] ?? '',
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['age'] ?? '',
                $row['region'] ?? '',
                $row['province'] ?? '',
                $row['city'] ?? '',
                AnalyticsExportDate::dateTime($row['registered_at'] ?? ''),
                $row['events_in_range'] ?? 0,
                AnalyticsExportDate::dateTime($row['last_active_at'] ?? ''),
            ];
        })->all();

        return [
            'name' => 'Users',
            'headers' => $compact
                ? ['Name', 'Email', 'Location', 'Registered', 'Events', 'Last active']
                : [
                    'ID', 'Name', 'Email', 'Age', 'Region', 'Province', 'City',
                    'Registered at', 'Events in range', 'Last active at',
                ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}
     */
    private function leaderboardSheet(AnalyticsDateRange $range, string $format = 'csv'): array
    {
        $compact = $format === 'pdf';
        $payload = $this->analyticsRepository->leaderboard($range, 'engagements', $compact ? 50 : 100);
        $rows = collect($payload['rows'] ?? [])->map(function ($row) use ($compact) {
            if ($compact) {
                return [
                    $row['rank'] ?? '',
                    $row['name'] ?? '',
                    $this->formatLocation($row),
                    (int) ($row['visits'] ?? 0),
                    (int) ($row['views'] ?? 0),
                    (int) ($row['engagements'] ?? 0),
                    (int) ($row['app_opens'] ?? 0),
                    (int) ($row['sessions'] ?? 0),
                    (int) ($row['engagement_score'] ?? 0),
                ];
            }

            return [
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
            ];
        })->all();

        return [
            'name' => 'Leaderboard',
            'headers' => $compact
                ? ['Rank', 'Name', 'Location', 'Visits', 'Views', 'Engaged', 'Opens', 'Sessions', 'Score']
                : [
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
            $cells = [AnalyticsExportDate::date($row['cohort'] ?? ''), $row['size'] ?? 0];
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
            AnalyticsExportDate::hour($row['hour'] ?? 0),
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
            $this->careToolsSheet($dashboard),
            ...$this->contentCatalogSheets($dashboard),
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>|null}>
     */
    private function contentCatalogSheets(array $dashboard): array
    {
        $catalog = $dashboard['content_catalog'] ?? [];
        $groups = is_array($catalog['groups'] ?? null) ? $catalog['groups'] : [];
        $sheets = [];

        foreach ($groups as $group) {
            $label = (string) ($group['label'] ?? 'Content');
            $rows = collect($group['items'] ?? [])->map(function ($item) {
                $data = is_array($item) ? $item : (array) $item;

                return [
                    $data['id'] ?? '',
                    $data['title'] ?? '',
                    (int) ($data['views'] ?? 0),
                    (int) ($data['shares'] ?? 0),
                    (int) ($data['downloads'] ?? 0),
                ];
            })->all();

            $viewLabel = (string) ($group['view_label'] ?? 'Views');
            $shareLabel = (string) ($group['share_label'] ?? 'Shares');
            $downloadLabel = (string) ($group['download_label'] ?? 'Downloads');

            $sheets[] = [
                'name' => $label,
                'headers' => ['ID', 'Title', $viewLabel, $shareLabel, $downloadLabel],
                'rows' => $rows,
                'chart' => [
                    'title' => $label.' '.$shareLabel,
                    'category_col' => 2,
                    'value_col' => 4,
                    'max_points' => 10,
                ],
            ];
        }

        return $sheets;
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

        $catalogGroups = collect($dashboard['content_catalog']['groups'] ?? []);
        $catalogCharts = [];
        foreach ([
            'iec_materials' => ['shares' => 'IEC shares', 'downloads' => 'IEC downloads'],
            'events' => ['shares' => 'Event shares'],
            'contests' => ['shares' => 'Contest shares'],
        ] as $groupKey => $metrics) {
            $group = $catalogGroups->firstWhere('key', $groupKey);
            if (! is_array($group)) {
                continue;
            }

            foreach ($metrics as $metric => $title) {
                $items = collect($group['items'] ?? [])
                    ->take(8)
                    ->map(fn ($item) => [
                        'label' => mb_strimwidth((string) ($item['title'] ?? 'Item'), 0, 28, '…'),
                        'value' => (int) ($item[$metric] ?? 0),
                    ])->all();
                $catalogCharts[] = ['title' => $title, 'items' => $items];
            }
        }

        return array_values(array_filter([
            ['title' => 'Top posts by views', 'items' => $posts],
            ['title' => 'Top categories', 'items' => $categories],
            ...$catalogCharts,
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
                AnalyticsExportDate::dateTime($row->detected_at),
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
    private function eventsSheet(AnalyticsDateRange $range, string $format = 'csv'): array
    {
        $compact = $format === 'pdf';
        $limit = $compact ? self::PDF_ROW_LIMIT : self::TABULAR_ROW_LIMIT;
        $total = $this->analyticsRepository->countForExport($range);
        $rows = $this->analyticsRepository->allForExport($range, $limit)->map(function ($row) use ($compact) {
            $eventType = $this->humanizeEventType((string) $row->event_type);
            $occurred = AnalyticsExportDate::dateTime($row->occurred_at ?? $row->created_at);
            $created = AnalyticsExportDate::dateTime($row->created_at);

            if ($compact) {
                return [
                    $row->id,
                    $eventType,
                    $row->post->title ?? ($row->screen_name ?? ''),
                    $row->user->email ?? '',
                    $row->platform ?? '',
                    $occurred,
                ];
            }

            return [
                $row->id,
                $eventType,
                $row->post_id ?? '',
                $row->post->title ?? '',
                $row->user_id ?? '',
                $row->user->email ?? '',
                $row->platform ?? '',
                $row->session_id ?? '',
                $row->screen_name ?? '',
                $row->resource_type ?? '',
                $row->resource_id ?? '',
                $occurred,
                $created,
            ];
        })->all();

        $sheet = [
            'name' => 'Events',
            'headers' => $compact
                ? ['ID', 'Event type', 'Title / screen', 'User email', 'Platform', 'Occurred at']
                : [
                    'ID', 'Event type', 'Post ID', 'Post title', 'User ID', 'User email',
                    'Platform', 'Session ID', 'Screen', 'Resource type', 'Resource ID', 'Occurred at', 'Created at',
                ],
            'rows' => $rows,
        ];

        if ($total > $limit) {
            $sheet['note'] = 'Showing latest '.number_format($limit).' of '.number_format($total)
                .' events. Use CSV or XLSX for a larger extract.';
        }

        return $sheet;
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
            $this->csvRow(['Generated', AnalyticsExportDate::dateTime(now())]),
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
            $worksheet->setCellValue(
                'A2',
                $dataset['subtitle'].' · '.$sheet['name'].' · Generated '.AnalyticsExportDate::dateTime(now()),
            );
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
                    $this->writeExcelValue($worksheet, $cell, $value);
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
    /**
     * @param  array<string, mixed>  $row
     */
    private function formatLocation(array $row): string
    {
        return collect([
            $row['city'] ?? null,
            $row['province'] ?? null,
            $row['region'] ?? null,
        ])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * @param  array{title: string, subtitle: string, sheets: list<array<string, mixed>>, charts?: list<array<string, mixed>>}  $dataset
     * @return array{title: string, subtitle: string, sheets: list<array<string, mixed>>, charts?: list<array<string, mixed>>}
     */
    private function formatDatasetCells(array $dataset): array
    {
        $dataset['sheets'] = array_map(function (array $sheet): array {
            $sheet['rows'] = array_map(function ($row): array {
                $cells = is_array($row) ? $row : [];

                return array_map(fn ($cell) => AnalyticsExportDate::cell($cell), $cells);
            }, $sheet['rows'] ?? []);

            return $sheet;
        }, $dataset['sheets'] ?? []);

        return $dataset;
    }

    /**
     * @param  array{title: string, subtitle: string, sheets: list<array<string, mixed>>, charts?: list<array<string, mixed>>}  $dataset
     * @return array{title: string, subtitle: string, sheets: list<array<string, mixed>>, charts?: list<array<string, mixed>>}
     */
    private function limitPdfDataset(array $dataset): array
    {
        $dataset['sheets'] = array_map(function (array $sheet): array {
            $rows = $sheet['rows'] ?? [];
            $total = count($rows);
            if ($total > self::PDF_ROW_LIMIT) {
                $sheet['rows'] = array_slice($rows, 0, self::PDF_ROW_LIMIT);
                $sheet['note'] = $sheet['note']
                    ?? 'Showing latest '.number_format(self::PDF_ROW_LIMIT).' of '.number_format($total).' rows.';
            }

            return $sheet;
        }, $dataset['sheets']);

        return $dataset;
    }

    private function toPdf(array $dataset): string
    {
        $wide = collect($dataset['sheets'] ?? [])
            ->contains(fn ($sheet) => count($sheet['headers'] ?? []) >= 7);

        $html = view('exports.analytics-report', [
            'title' => $dataset['title'],
            'subtitle' => $dataset['subtitle'],
            'sheets' => $dataset['sheets'],
            'charts' => $dataset['charts'] ?? [],
            'generatedAt' => AnalyticsExportDate::dateTime(now()),
            'sealSrc' => $this->sealDataUri(),
            'wide' => $wide,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([
            base_path(),
            public_path(),
            storage_path(),
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $wide ? 'landscape' : 'portrait');
        $dompdf->render();

        try {
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            if ($canvas && $font) {
                $canvas->page_text(
                    $canvas->get_width() - 120,
                    $canvas->get_height() - 22,
                    'Page {PAGE_NUM} of {PAGE_COUNT}',
                    $font,
                    8,
                    [0.29, 0.33, 0.41],
                );
            }
        } catch (Throwable) {
            // Page numbers are optional if the PDF engine cannot paint them.
        }

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

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>, chart?: array<string, mixed>}
     */
    private function careToolsSheet(array $dashboard): array
    {
        $totals = is_array($dashboard['care_tools']['totals'] ?? null)
            ? $dashboard['care_tools']['totals']
            : [];
        $features = is_array($dashboard['care_tools']['features'] ?? null)
            ? $dashboard['care_tools']['features']
            : [];

        $rows = [
            ['Journal saves', (int) ($totals['journal_creates'] ?? 0)],
            ['Care checks', (int) ($totals['care_checks'] ?? 0)],
            ['Mood check-ins', (int) ($totals['mood_checkins'] ?? 0)],
            ['Calm sessions', (int) ($totals['calm_sessions'] ?? 0)],
            ['Color Fun opens', (int) ($totals['color_fun_opens'] ?? 0)],
            ['Color Fun completes', (int) ($totals['color_fun_completes'] ?? 0)],
            ['Notification opens', (int) ($totals['notification_opens'] ?? 0)],
            ['Contest submissions', (int) ($totals['contest_submits'] ?? 0)],
            ['Toolkit opens', (int) ($totals['toolkit_opens'] ?? 0)],
            ['Get Support opens', (int) ($totals['support_opens'] ?? 0)],
            ['Kid Listo opens', (int) ($totals['kid_listo_opens'] ?? 0)],
            ['Sticker attaches', (int) ($totals['sticker_uses'] ?? 0)],
        ];

        foreach ($features as $feature => $count) {
            $known = in_array((string) $feature, ['care_toolkit', 'care_support', 'kid_listo', 'sticker'], true);
            if ($known) {
                continue;
            }
            $rows[] = ['Feature: '.$this->humanizeEventType((string) $feature), (int) $count];
        }

        return [
            'name' => 'Care and tools',
            'headers' => ['Metric', 'Total'],
            'rows' => $rows,
            'chart' => ['title' => 'Care and tools', 'category_col' => 1, 'value_col' => 2],
        ];
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @return list<list<mixed>>
     */
    private function hourlyRows(iterable $rows): array
    {
        $items = collect($rows)->map(fn ($row) => [
            AnalyticsExportDate::hour(is_array($row) ? ($row['hour'] ?? 0) : 0),
            (int) (is_array($row) ? ($row['total'] ?? 0) : 0),
        ])->all();

        return $this->rowsWithPeakShare($items);
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<list<mixed>>
     */
    private function rowsWithPeakShare(array $rows): array
    {
        $max = max(1, (int) collect($rows)->max(fn ($row) => (int) ($row[1] ?? 0)));

        return array_map(function (array $row) use ($max) {
            $value = (int) ($row[1] ?? 0);
            $row[] = round(($value / $max) * 100, 1);

            return $row;
        }, $rows);
    }

    private function humanizeEventType(string $type): string
    {
        $canonical = AnalyticsEventTypes::canonicalize(trim($type));

        return match ($canonical) {
            'screen_view' => 'Screen view',
            'page_view' => 'Page view',
            'app_open' => 'App open',
            'session_start' => 'Session start',
            'post_view' => 'Post views',
            'post_like' => 'Post likes',
            'post_comment' => 'Post comments',
            'iec_view' => 'IEC views',
            'iec_share' => 'IEC shares',
            'iec_download' => 'IEC downloads',
            'event_view' => 'Event views',
            'event_share' => 'Event shares',
            'event_register' => 'Event registrations',
            'contest_view' => 'Contest views',
            'contest_share' => 'Contest shares',
            'contest_submit' => 'Contest submissions',
            'rehab_view' => 'Rehab views',
            'rehab_call' => 'Rehab calls',
            'rehab_directions' => 'Rehab directions',
            'hope_org_view' => 'Hope directory views',
            'journal_create' => 'Journal saves',
            'care_check_submit' => 'Care checks',
            'mood_checkin' => 'Mood check-ins',
            'calm_complete' => 'Calm sessions',
            'color_fun_open' => 'Color Fun opens',
            'color_fun_complete' => 'Color Fun completes',
            'notification_open' => 'Notification opens',
            'feature_use' => 'Feature use',
            'care_toolkit' => 'Self-care Toolkit',
            'care_support' => 'Get Support',
            'kid_listo' => 'Kid Listo',
            default => $canonical === ''
                ? 'Unknown'
                : ucwords(str_replace('_', ' ', $canonical)),
        };
    }

    private function writeExcelValue(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,
        string $cell,
        mixed $value,
    ): void {
        if (is_int($value) || is_float($value)) {
            $worksheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);

            return;
        }

        if (is_string($value) && is_numeric($value) && ! str_contains($value, ':') && ! preg_match('/^0\d+$/', $value)) {
            $worksheet->setCellValueExplicit($cell, 0 + $value, DataType::TYPE_NUMERIC);

            return;
        }

        $worksheet->setCellValue($cell, $value);
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
