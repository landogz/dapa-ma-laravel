<?php

namespace App\Services\Analytics;

use App\Jobs\Analytics\ProcessAnalyticsExportJob;
use App\Models\AnalyticsExport;
use App\Models\User;

class AnalyticsExportService
{
    /**
     * @param  array{report_type: string, format: string, range?: string|null, from?: string|null, to?: string|null, columns?: array<int, string>|null, filters?: array<string, mixed>|null}  $payload
     */
    public function queue(User $user, array $payload): AnalyticsExport
    {
        $export = AnalyticsExport::query()->create([
            'requested_by' => $user->id,
            'report_type' => $payload['report_type'],
            'format' => $payload['format'],
            'status' => 'pending',
            'filters' => array_merge($payload['filters'] ?? [], [
                'range' => $payload['range'] ?? '30',
                'from' => $payload['from'] ?? null,
                'to' => $payload['to'] ?? null,
            ]),
            'columns' => $payload['columns'] ?? null,
        ]);

        ProcessAnalyticsExportJob::dispatch($export->id)->afterResponse();

        return $export;
    }

    public function processPendingFor(User $user, int $limit = 3): void
    {
        AnalyticsExport::query()
            ->where('requested_by', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(fn (int $id) => ProcessAnalyticsExportJob::dispatchSync($id));
    }
}
