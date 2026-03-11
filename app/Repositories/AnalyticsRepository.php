<?php

namespace App\Repositories;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository
{
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
            ->where('event_type', 'post_view')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('post_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->with('post:id,title')
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

    public function recentEvents(int $limit = 10): Collection
    {
        return AnalyticsEvent::query()
            ->with(['post:id,title', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'event_type', 'post_id', 'user_id', 'session_id', 'created_at']);
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

        $android = (clone $baseQuery)
            ->where('event_type', 'like', '%android%')
            ->count();

        $ios = (clone $baseQuery)
            ->where('event_type', 'like', '%ios%')
            ->count();

        return [
            'android' => $android,
            'ios'     => $ios,
            'total'   => $android + $ios,
        ];
    }
}
