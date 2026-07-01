<?php

namespace App\Repositories;

use App\Models\AnalyticsEvent;
use App\Models\Post;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository
{
    private const VIEW_EVENT_TYPES = ['post_view', 'view'];

    public function countByEventType(int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->select('event_type', DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->get();
    }

    public function topPosts(int $limit = 10, int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->select('post_id', DB::raw('count(*) as views'))
            ->whereNotNull('post_id')
            ->whereIn('event_type', self::VIEW_EVENT_TYPES)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('post_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->with(['post' => function ($query) {
                $query->select('id', 'title')
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating');
            }])
            ->get();
    }

    public function ratingSummary(int $days = 30): array
    {
        $baseQuery = Review::query()
            ->where('target_type', Post::class)
            ->where('created_at', '>=', now()->subDays($days));

        $total = (clone $baseQuery)->count();
        $average = (float) (clone $baseQuery)->avg('rating');

        $previousStart = now()->subDays($days * 2);
        $previousEnd = now()->subDays($days);
        $previousQuery = Review::query()
            ->where('target_type', Post::class)
            ->where('created_at', '>=', $previousStart)
            ->where('created_at', '<', $previousEnd);

        $previousTotal = $previousQuery->count();

        return [
            'total_reviews'  => $total,
            'average_rating' => round($average, 1),
            'previous_total' => $previousTotal,
        ];
    }

    public function topRatedPosts(int $limit = 10): Collection
    {
        return Post::query()
            ->select(['id', 'title'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->whereHas('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->limit($limit)
            ->get();
    }

    public function dailyEventCounts(int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function dailyEventCountsFilled(int $days = 30): array
    {
        $raw = $this->dailyEventCounts($days)->keyBy(fn ($row) => (string) $row->date);
        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset -= 1) {
            $date = now()->subDays($offset)->toDateString();
            $series[] = [
                'date'  => $date,
                'total' => (int) ($raw[$date]->total ?? 0),
            ];
        }

        return $series;
    }

    public function dailyCountsByEventType(int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                'event_type',
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get();
    }

    public function periodComparison(int $days = 30): array
    {
        $now = now();
        $currentStart = $now->copy()->subDays($days);
        $previousStart = $now->copy()->subDays($days * 2);
        $previousEnd = $currentStart->copy();

        return [
            'current'  => $this->metricSummaryBetween($currentStart, $now),
            'previous' => $this->metricSummaryBetween($previousStart, $previousEnd),
            'label'    => $days === 1 ? 'yesterday' : "previous {$days} days",
        ];
    }

    public function recentEvents(int $limit = 10, int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->with(['post:id,title', 'user:id,name'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'event_type', 'post_id', 'user_id', 'session_id', 'platform', 'created_at']);
    }

    public function allForExport(int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->with(['post:id,title', 'user:id,name,email'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->get();
    }

    public function deviceBreakdown(int $days = 30): array
    {
        $baseQuery = AnalyticsEvent::query()
            ->where('created_at', '>=', now()->subDays($days));

        $android = (clone $baseQuery)->where('platform', 'android')->count();
        $ios = (clone $baseQuery)->where('platform', 'ios')->count();
        $web = (clone $baseQuery)->where('platform', 'web')->count();
        $other = (clone $baseQuery)
            ->where(function ($query) {
                $query->whereNull('platform')
                    ->orWhereNotIn('platform', ['android', 'ios', 'web']);
            })
            ->count();

        return [
            'android' => $android,
            'ios'     => $ios,
            'web'     => $web,
            'other'   => $other,
            'total'   => $android + $ios + $web + $other,
        ];
    }

    private function metricSummaryBetween(Carbon $from, Carbon $to): array
    {
        $events = AnalyticsEvent::query()
            ->select('event_type', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->groupBy('event_type')
            ->get();

        return $this->summarizeMetrics($events);
    }

    private function summarizeMetrics(Collection $events): array
    {
        $summary = [
            'views'     => 0,
            'bookmarks' => 0,
            'searches'  => 0,
            'shares'    => 0,
        ];

        foreach ($events as $event) {
            $type = strtolower((string) $event->event_type);
            $total = (int) $event->total;

            if (str_contains($type, 'view')) {
                $summary['views'] += $total;
            } elseif (str_contains($type, 'bookmark')) {
                $summary['bookmarks'] += $total;
            } elseif (str_contains($type, 'search')) {
                $summary['searches'] += $total;
            } elseif (str_contains($type, 'share')) {
                $summary['shares'] += $total;
            }
        }

        return $summary;
    }
}
