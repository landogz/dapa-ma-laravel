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
                $query->select('id', 'title', 'category_id')
                    ->with(['category:id,name'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating');
            }])
            ->get();
    }

    public function audienceSummary(int $days = 30): array
    {
        $from = now()->subDays($days);
        $baseQuery = AnalyticsEvent::query()->where('created_at', '>=', $from);

        $totalEvents = (clone $baseQuery)->count();
        $uniqueUsers = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
        $uniqueSessions = (clone $baseQuery)
            ->whereNotNull('session_id')
            ->distinct()
            ->count('session_id');
        $signedInEvents = (clone $baseQuery)->whereNotNull('user_id')->count();
        $anonymousEvents = max(0, $totalEvents - $signedInEvents);

        return [
            'total_events'     => $totalEvents,
            'unique_users'     => $uniqueUsers,
            'unique_sessions'  => $uniqueSessions,
            'signed_in_events' => $signedInEvents,
            'anonymous_events' => $anonymousEvents,
            'avg_per_day'      => $days > 0 ? round($totalEvents / $days, 1) : 0,
        ];
    }

    public function hourlyCounts(int $days = 30): array
    {
        $expression = $this->hourExpression();
        $raw = AnalyticsEvent::query()
            ->select(DB::raw("{$expression} as hour"), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw($expression))
            ->pluck('total', 'hour');

        $series = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $series[] = [
                'hour'  => $hour,
                'total' => (int) ($raw[$hour] ?? $raw[(string) $hour] ?? 0),
            ];
        }

        return $series;
    }

    public function weekdayCounts(int $days = 30): array
    {
        $expression = $this->weekdayExpression();
        $raw = AnalyticsEvent::query()
            ->select(DB::raw("{$expression} as weekday"), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw($expression))
            ->pluck('total', 'weekday');

        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $series = [];

        for ($weekday = 0; $weekday < 7; $weekday++) {
            $series[] = [
                'weekday' => $weekday,
                'label'   => $labels[$weekday],
                'total'   => (int) ($raw[$weekday] ?? $raw[(string) $weekday] ?? 0),
            ];
        }

        return array_merge(array_slice($series, 1), [$series[0]]);
    }

    public function ratingDistribution(): array
    {
        $raw = Review::query()
            ->select('rating', DB::raw('count(*) as total'))
            ->where('target_type', Post::class)
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $series = [];

        for ($star = 5; $star >= 1; $star--) {
            $series[] = [
                'rating' => $star,
                'total'  => (int) ($raw[$star] ?? $raw[(string) $star] ?? 0),
            ];
        }

        return $series;
    }

    public function topCategories(int $limit = 6, int $days = 30): Collection
    {
        return AnalyticsEvent::query()
            ->select('categories.id', 'categories.name', DB::raw('count(*) as views'))
            ->join('posts', 'posts.id', '=', 'analytics_events.post_id')
            ->join('categories', 'categories.id', '=', 'posts.category_id')
            ->whereIn('analytics_events.event_type', self::VIEW_EVENT_TYPES)
            ->where('analytics_events.created_at', '>=', now()->subDays($days))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('views')
            ->limit($limit)
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

        $summary = $this->summarizeMetrics($events);
        $baseQuery = AnalyticsEvent::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to);

        $summary['total_events'] = (clone $baseQuery)->count();
        $summary['unique_users'] = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
        $summary['unique_sessions'] = (clone $baseQuery)
            ->whereNotNull('session_id')
            ->distinct()
            ->count('session_id');

        return $summary;
    }

    private function hourExpression(): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';
    }

    private function weekdayExpression(): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%w', created_at) AS INTEGER)"
            : '(DAYOFWEEK(created_at) - 1)';
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
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
