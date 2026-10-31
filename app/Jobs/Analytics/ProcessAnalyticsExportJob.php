<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsExport;
use App\Repositories\AnalyticsRepository;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessAnalyticsExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $exportId,
    ) {
    }

    public function handle(AnalyticsService $analyticsService, AnalyticsRepository $repository): void
    {
        $export = AnalyticsExport::query()->find($this->exportId);
        if (! $export) {
            return;
        }

        $export->update(['status' => 'processing']);

        try {
            $filters = $export->filters ?? [];
            $range = AnalyticsDateRange::fromRequest([
                'range' => $filters['range'] ?? '30',
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ]);

            $format = $export->format;
            $basename = sprintf(
                'analytics-%s-%s-%s.%s',
                $export->report_type,
                $range->key,
                now()->format('YmdHis'),
                $format === 'xlsx' ? 'csv' : ($format === 'pdf' ? 'txt' : 'csv'),
            );
            // Generate filter-aware CSV payload for all formats first (XLSX/PDF packages may be unavailable).
            $body = match ($export->report_type) {
                'users' => $this->usersCsv($repository, $range, $filters),
                'retention' => $this->jsonAsCsv($repository->retentionCohorts($range)),
                'leaderboard' => $this->jsonAsCsv($repository->leaderboard($range)),
                'geography' => $this->jsonAsCsv($repository->geographicDistribution($range)),
                'journeys' => $this->jsonAsCsv($repository->funnelReport('default_content', $range)),
                'alerts' => $this->alertsCsv(),
                default => $analyticsService->exportCsv($range),
            };

            if ($format === 'pdf') {
                $body = "DAPE-MA Analytics Export\n"
                    ."Report: {$export->report_type}\n"
                    .'Range: '.$range->label."\n"
                    .'Generated: '.now()->toDateTimeString()."\n\n"
                    .$body;
            }

            $path = 'analytics-exports/'.$basename;
            Storage::disk('local')->put($path, $body);

            $export->update([
                'status' => 'ready',
                'disk_path' => $path,
                'download_name' => $basename,
                'ready_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function usersCsv(AnalyticsRepository $repository, AnalyticsDateRange $range, array $filters): string
    {
        $page = $repository->filterUsers($filters, $range, 5000);
        $lines = [$this->csvRow([
            'id', 'name', 'email', 'age', 'region', 'province', 'city', 'registered_at', 'events_in_range', 'last_active_at',
        ])];
        foreach ($page['data'] as $row) {
            $lines[] = $this->csvRow([
                $row['id'], $row['name'], $row['email'], $row['age'] ?? '',
                $row['region'] ?? '', $row['province'] ?? '', $row['city'] ?? '',
                $row['registered_at'] ?? '', $row['events_in_range'] ?? 0, $row['last_active_at'] ?? '',
            ]);
        }

        return "\u{FEFF}".implode("\n", $lines);
    }

    private function alertsCsv(): string
    {
        $rows = \App\Models\AnalyticsAnomalyAlert::query()->orderByDesc('detected_at')->limit(1000)->get();
        $lines = [$this->csvRow(['id', 'title', 'metric_key', 'previous_value', 'current_value', 'percent_change', 'severity', 'status', 'detected_at'])];
        foreach ($rows as $row) {
            $lines[] = $this->csvRow([
                $row->id, $row->title, $row->metric_key, $row->previous_value, $row->current_value,
                $row->percent_change, $row->severity, $row->status, optional($row->detected_at)?->toIso8601String(),
            ]);
        }

        return "\u{FEFF}".implode("\n", $lines);
    }

    private function jsonAsCsv(array $payload): string
    {
        return "\u{FEFF}".$this->csvRow(['json'])."\n".$this->csvRow([json_encode($payload)]);
    }

    private function csvRow(array $columns): string
    {
        return implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $columns));
    }
}
