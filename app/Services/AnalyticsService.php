<?php

namespace App\Services;

use App\Repositories\AnalyticsRepository;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $analyticsRepository,
    ) {
    }

    public function dashboard(int $days = 30): array
    {
        return [
            'by_event_type'        => $this->analyticsRepository->countByEventType($days),
            'top_posts'            => $this->analyticsRepository->topPosts(10, $days),
            'daily_counts'         => $this->analyticsRepository->dailyEventCountsFilled($days),
            'daily_by_event_type'  => $this->analyticsRepository->dailyCountsByEventType($days),
            'period_comparison'    => $this->analyticsRepository->periodComparison($days),
            'recent_events'        => $this->analyticsRepository->recentEvents(10, $days),
            'devices'              => $this->analyticsRepository->deviceBreakdown($days),
            'generated_at'         => now()->toIso8601String(),
        ];
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
                $row->created_at->toIso8601String(),
            ]);
        }

        return implode("\n", $lines);
    }

    private function csvRow(array $columns): string
    {
        return implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $columns));
    }
}
