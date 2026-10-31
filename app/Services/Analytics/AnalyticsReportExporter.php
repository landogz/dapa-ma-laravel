<?php

namespace App\Services\Analytics;

use App\Repositories\AnalyticsRepository;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Dompdf\Dompdf;
use Dompdf\Options;
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
            ['key' => 'summary', 'label' => 'Overview summary'],
            ['key' => 'events', 'label' => 'Event log'],
            ['key' => 'demographics', 'label' => 'Demographics (Region / Province / City)'],
            ['key' => 'users', 'label' => 'Users'],
            ['key' => 'leaderboard', 'label' => 'Top users leaderboard'],
            ['key' => 'retention', 'label' => 'Retention cohorts'],
            ['key' => 'mobile', 'label' => 'Mobile / devices'],
            ['key' => 'content', 'label' => 'Content performance'],
            ['key' => 'journeys', 'label' => 'Journeys / funnel'],
            ['key' => 'alerts', 'label' => 'Anomaly alerts'],
            ['key' => 'complete', 'label' => 'Complete pack (CSV sheets as sections)'],
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
     * @return array{title: string, subtitle: string, sheets: list<array{name: string, headers: list<string>, rows: list<list<string|int|float|null>>}>}
     */
    private function dataset(string $reportType, AnalyticsDateRange $range): array
    {
        $subtitle = 'Range: '.$range->label.' · Generated '.$range->from->toDateString().' to '.$range->to->toDateString();

        return match ($reportType) {
            'summary' => [
                'title' => 'DAPE-MA Analytics — Overview summary',
                'subtitle' => $subtitle,
                'sheets' => $this->summarySheets($range),
            ],
            'demographics' => [
                'title' => 'DAPE-MA Analytics — Demographics',
                'subtitle' => $subtitle.' · Profile Region / Province / City',
                'sheets' => $this->demographicsSheets($range),
            ],
            'users' => [
                'title' => 'DAPE-MA Analytics — Users',
                'subtitle' => $subtitle,
                'sheets' => [$this->usersSheet($range)],
            ],
            'leaderboard' => [
                'title' => 'DAPE-MA Analytics — Top users leaderboard',
                'subtitle' => $subtitle,
                'sheets' => [$this->leaderboardSheet($range)],
            ],
            'retention' => [
                'title' => 'DAPE-MA Analytics — Retention',
                'subtitle' => $subtitle,
                'sheets' => [$this->retentionSheet($range)],
            ],
            'mobile' => [
                'title' => 'DAPE-MA Analytics — Mobile / devices',
                'subtitle' => $subtitle,
                'sheets' => $this->mobileSheets($range),
            ],
            'content' => [
                'title' => 'DAPE-MA Analytics — Content performance',
                'subtitle' => $subtitle,
                'sheets' => $this->contentSheets($range),
            ],
            'journeys' => [
                'title' => 'DAPE-MA Analytics — Journeys',
                'subtitle' => $subtitle,
                'sheets' => [$this->journeysSheet($range)],
            ],
            'alerts' => [
                'title' => 'DAPE-MA Analytics — Anomaly alerts',
                'subtitle' => $subtitle,
                'sheets' => [$this->alertsSheet()],
            ],
            'complete' => [
                'title' => 'DAPE-MA Analytics — Complete pack',
                'subtitle' => $subtitle,
                'sheets' => array_merge(
                    $this->summarySheets($range),
                    $this->demographicsSheets($range),
                    [$this->leaderboardSheet($range)],
                    $this->contentSheets($range),
                    $this->mobileSheets($range),
                ),
            ],
            default => [
                'title' => 'DAPE-MA Analytics — Event log',
                'subtitle' => $subtitle,
                'sheets' => [$this->eventsSheet($range)],
            ],
        };
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    private function summarySheets(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $kpis = $dashboard['kpis'] ?? [];
        $current = $dashboard['period_comparison']['current'] ?? [];
        $funnel = $dashboard['funnel'] ?? [];

        $kpiRows = [
            ['Registered users', $kpis['registered_users'] ?? 0],
            ['New users', $kpis['new_users'] ?? 0],
            ['Daily active users', $kpis['dau'] ?? 0],
            ['Weekly active users', $kpis['wau'] ?? 0],
            ['Monthly active users', $kpis['mau'] ?? 0],
            ['App opens', $kpis['app_opens'] ?? 0],
            ['Avg session (seconds)', $kpis['avg_session_duration_seconds'] ?? 0],
            ['Views', $current['views'] ?? 0],
            ['Bookmarks', $current['bookmarks'] ?? 0],
            ['Shares', $current['shares'] ?? 0],
            ['Engagement rate %', $funnel['engagement'] ?? 0],
            ['Total events', $current['total_events'] ?? ($dashboard['audience']['total_events'] ?? 0)],
        ];

        $daily = collect($dashboard['daily_counts'] ?? [])->map(fn ($row) => [
            $row['date'] ?? '',
            $row['total'] ?? 0,
        ])->all();

        $types = collect($dashboard['by_event_type'] ?? [])->map(fn ($row) => [
            $row['event_type'] ?? '',
            $row['total'] ?? 0,
        ])->all();

        return [
            [
                'name' => 'KPIs',
                'headers' => ['Metric', 'Value'],
                'rows' => $kpiRows,
            ],
            [
                'name' => 'Daily activity',
                'headers' => ['Date', 'Events'],
                'rows' => $daily,
            ],
            [
                'name' => 'Event types',
                'headers' => ['Event type', 'Total'],
                'rows' => $types,
            ],
        ];
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    private function demographicsSheets(AnalyticsDateRange $range): array
    {
        $overview = $this->analyticsRepository->geographicOverview($range, 100);
        $summary = $overview['summary'] ?? [];

        $mapLevel = function (array $block) {
            return collect($block['rows'] ?? [])->map(fn ($row) => [
                $row['location'] ?? '',
                $row['registered'] ?? 0,
                $row['active'] ?? 0,
            ])->all();
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
                'headers' => ['Region', 'Registered', 'Active'],
                'rows' => $mapLevel($overview['region'] ?? []),
            ],
            [
                'name' => 'Province',
                'headers' => ['Province', 'Registered', 'Active'],
                'rows' => $mapLevel($overview['province'] ?? []),
            ],
            [
                'name' => 'City Municipality',
                'headers' => ['City / Municipality', 'Registered', 'Active'],
                'rows' => $mapLevel($overview['city'] ?? []),
            ],
        ];
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
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function leaderboardSheet(AnalyticsDateRange $range): array
    {
        $payload = $this->analyticsRepository->leaderboard($range, 'engagements', 100);
        $rows = collect($payload['rows'] ?? [])->map(fn ($row) => [
            $row['rank'] ?? '',
            $row['user_id'] ?? '',
            $row['name'] ?? '',
            $row['visits'] ?? 0,
            $row['app_usage'] ?? 0,
            $row['views'] ?? 0,
            $row['engagements'] ?? 0,
            $row['engagement_score'] ?? 0,
            $row['metric_value'] ?? 0,
        ])->all();

        return [
            'name' => 'Leaderboard',
            'headers' => [
                'Rank', 'User ID', 'Name', 'Visits', 'App usage', 'Content views',
                'Engagement', 'Engagement score', 'Metric value',
            ],
            'rows' => $rows,
        ];
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
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    private function mobileSheets(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $devices = $dashboard['devices'] ?? [];
        $screens = collect($dashboard['screen_breakdown'] ?? [])->map(fn ($row) => [
            $row['screen_name'] ?? ($row['name'] ?? ''),
            $row['total'] ?? ($row['count'] ?? 0),
        ])->all();

        return [
            [
                'name' => 'Devices',
                'headers' => ['Platform', 'Count'],
                'rows' => [
                    ['Android', $devices['android'] ?? 0],
                    ['iOS', $devices['ios'] ?? 0],
                    ['Web', $devices['web'] ?? 0],
                    ['Other', $devices['other'] ?? 0],
                ],
            ],
            [
                'name' => 'Screens',
                'headers' => ['Screen', 'Events'],
                'rows' => $screens,
            ],
        ];
    }

    /**
     * @return list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    private function contentSheets(AnalyticsDateRange $range): array
    {
        $dashboard = $this->analyticsService->dashboard($range);
        $topPosts = collect($dashboard['top_posts'] ?? [])->map(fn ($row) => [
            $row['post']['id'] ?? ($row['post_id'] ?? ''),
            $row['post']['title'] ?? '',
            $row['views'] ?? 0,
        ])->all();
        $categories = collect($dashboard['top_categories'] ?? [])->map(fn ($row) => [
            $row['name'] ?? '',
            $row['views'] ?? 0,
        ])->all();

        return [
            [
                'name' => 'Top posts',
                'headers' => ['Post ID', 'Title', 'Views'],
                'rows' => $topPosts,
            ],
            [
                'name' => 'Top categories',
                'headers' => ['Category', 'Views'],
                'rows' => $categories,
            ],
        ];
    }

    /**
     * @return array{name: string, headers: list<string>, rows: list<list<mixed>>}
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
        ];
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
        }

        $spreadsheet->setActiveSheetIndex(0);

        $temp = fopen('php://temp', 'r+');
        (new Xlsx($spreadsheet))->save($temp);
        rewind($temp);
        $body = stream_get_contents($temp) ?: '';
        fclose($temp);
        $spreadsheet->disconnectWorksheets();

        return $body;
    }

    /**
     * @param  array{title: string, subtitle: string, sheets: list<array{name: string, headers: list<string>, rows: list<list<mixed>>}>}  $dataset
     */
    private function toPdf(array $dataset): string
    {
        $html = view('exports.analytics-report', [
            'title' => $dataset['title'],
            'subtitle' => $dataset['subtitle'],
            'sheets' => $dataset['sheets'],
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
