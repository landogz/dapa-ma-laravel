<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsExport;
use App\Services\Analytics\AnalyticsReportExporter;
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

    public function handle(AnalyticsReportExporter $exporter): void
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

            $file = $exporter->export(
                (string) $export->report_type,
                (string) $export->format,
                $range,
            );

            $path = 'analytics-exports/'.$file['filename'];
            Storage::disk('local')->put($path, $file['body']);

            $export->update([
                'status' => 'ready',
                'disk_path' => $path,
                'download_name' => $file['filename'],
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
}
