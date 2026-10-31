<?php

namespace App\Repositories;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\Bookmark;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\Review;
use App\Models\User;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsEventTypes;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository
{
    public function countByEventType(AnalyticsDateRange $range): Collection
    {
        return $this->eventsInRange($range)
            ->select('event_type', DB::raw('count(*) as total'))
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->get();
    }

    public function topPosts(AnalyticsDateRange $range, int $limit = 10): Collection
    {
        $rows = $this->eventsInRange($range)
            ->select('post_id', DB::raw('count(*) as views'))
            ->whereNotNull('post_id')
            ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
            ->groupBy('post_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->with(['post' => function ($query) {
                $query->select('id', 'title', 'category_id', 'media_url')
                    ->with(['category:id,name'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating');
            }])
            ->get();

        $postIds = $rows->pluck('post_id')->filter()->all();
        $previousViews = [];

        if ($postIds !== []) {
            $previousViews = $this->eventsBetween($range->previousFrom, $range->previousTo)
                ->select('post_id', DB::raw('count(*) as views'))
                ->whereIn('post_id', $postIds)
                ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
                ->groupBy('post_id')
                ->pluck('views', 'post_id')
                ->all();
        }

        return $rows->map(function ($row) use ($previousViews) {
            $row->previous_views = (int) ($previousViews[$row->post_id] ?? 0);

            return $row;
        });
    }

    public function audienceSummary(AnalyticsDateRange $range): array
    {
        $baseQuery = $this->eventsInRange($range);

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
        $days = $range->daySpan();

        return [
            'total_events' => $totalEvents,
            'unique_users' => $uniqueUsers,
            'unique_sessions' => $uniqueSessions,
            'signed_in_events' => $signedInEvents,
            'anonymous_events' => $anonymousEvents,
            'avg_per_day' => $days > 0 ? round($totalEvents / $days, 1) : 0,
        ];
    }

    public function hourlyCounts(AnalyticsDateRange $range): array
    {
        $expression = $this->hourExpression();
        $raw = $this->eventsInRange($range)
            ->select(DB::raw("{$expression} as hour"), DB::raw('count(*) as total'))
            ->groupBy(DB::raw($expression))
            ->pluck('total', 'hour');

        $series = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $series[] = [
                'hour' => $hour,
                'total' => (int) ($raw[$hour] ?? $raw[(string) $hour] ?? 0),
            ];
        }

        return $series;
    }

    public function weekdayCounts(AnalyticsDateRange $range): array
    {
        $expression = $this->weekdayExpression();
        $raw = $this->eventsInRange($range)
            ->select(DB::raw("{$expression} as weekday"), DB::raw('count(*) as total'))
            ->groupBy(DB::raw($expression))
            ->pluck('total', 'weekday');

        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $series = [];
        for ($weekday = 0; $weekday < 7; $weekday++) {
            $series[] = [
                'weekday' => $weekday,
                'label' => $labels[$weekday],
                'total' => (int) ($raw[$weekday] ?? $raw[(string) $weekday] ?? 0),
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
                'total' => (int) ($raw[$star] ?? $raw[(string) $star] ?? 0),
            ];
        }

        return $series;
    }

    public function topCategories(AnalyticsDateRange $range, int $limit = 6): Collection
    {
        return $this->eventsInRange($range)
            ->select('categories.id', 'categories.name', DB::raw('count(*) as views'))
            ->join('posts', 'posts.id', '=', 'analytics_events.post_id')
            ->join('categories', 'categories.id', '=', 'posts.category_id')
            ->whereIn('analytics_events.event_type', AnalyticsEventTypes::viewTypes())
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    public function ratingSummary(AnalyticsDateRange $range): array
    {
        $baseQuery = Review::query()
            ->where('target_type', Post::class)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to);

        $total = (clone $baseQuery)->count();
        $average = (float) (clone $baseQuery)->avg('rating');

        $previousQuery = Review::query()
            ->where('target_type', Post::class)
            ->where('created_at', '>=', $range->previousFrom)
            ->where('created_at', '<', $range->previousTo);

        return [
            'total_reviews' => $total,
            'average_rating' => round($average, 1),
            'previous_total' => $previousQuery->count(),
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

    public function dailyEventCountsFilled(AnalyticsDateRange $range): array
    {
        $raw = $this->eventsInRange($range)
            ->select(DB::raw('DATE(COALESCE(occurred_at, created_at)) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => (string) $row->date);

        $series = [];
        $cursor = $range->from->copy()->startOfDay();
        $end = $range->to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $series[] = [
                'date' => $date,
                'total' => (int) ($raw[$date]->total ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    public function dailyCountsByEventType(AnalyticsDateRange $range): Collection
    {
        return $this->eventsInRange($range)
            ->select(
                DB::raw('DATE(COALESCE(occurred_at, created_at)) as date'),
                'event_type',
                DB::raw('count(*) as total')
            )
            ->groupBy('date', 'event_type')
            ->orderBy('date')
            ->get();
    }

    public function periodComparison(AnalyticsDateRange $range): array
    {
        return [
            'current' => $this->metricSummaryBetween($range->from, $range->to),
            'previous' => $this->metricSummaryBetween($range->previousFrom, $range->previousTo),
            'label' => 'previous period',
        ];
    }

    public function recentEvents(AnalyticsDateRange $range, int $limit = 40): Collection
    {
        return $this->eventsInRange($range)
            ->with(['post:id,title', 'user:id,name'])
            ->orderByDesc(DB::raw('COALESCE(occurred_at, created_at)'))
            ->limit($limit)
            ->get([
                'id', 'event_type', 'post_id', 'user_id', 'session_id', 'platform',
                'screen_name', 'resource_type', 'resource_id', 'occurred_at', 'created_at',
            ]);
    }

    public function allForExport(AnalyticsDateRange $range): Collection
    {
        return $this->eventsInRange($range)
            ->with(['post:id,title', 'user:id,name,email'])
            ->orderByDesc(DB::raw('COALESCE(occurred_at, created_at)'))
            ->get();
    }

    public function deviceBreakdown(AnalyticsDateRange $range): array
    {
        $baseQuery = $this->eventsInRange($range);
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
            'ios' => $ios,
            'web' => $web,
            'other' => $other,
            'total' => $android + $ios + $web + $other,
        ];
    }

    public function kpiExtras(AnalyticsDateRange $range): array
    {
        $appUsers = User::query()->where('role', 'app_user')->count();
        if ($appUsers === 0) {
            $appUsers = User::query()
                ->whereNotIn('role', ['super_admin', 'editor', 'publisher', 'analytics_viewer'])
                ->count();
        }

        $newUsers = User::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->where(function ($q) {
                $q->where('role', 'app_user')
                    ->orWhereNotIn('role', ['super_admin', 'editor', 'publisher', 'analytics_viewer']);
            })
            ->count();

        $sessionQuery = AnalyticsSession::query()
            ->where('started_at', '>=', $range->from)
            ->where('started_at', '<=', $range->to);

        $totalSessions = (clone $sessionQuery)->count();
        $avgDuration = (float) (clone $sessionQuery)->avg('duration_seconds');
        $appOpens = $this->eventsInRange($range)
            ->where('event_type', AnalyticsEventTypes::APP_OPEN)
            ->count();

        $dau = $this->activeUsersForDays(1);
        $wau = $this->activeUsersForDays(7);
        $mau = $this->activeUsersForDays(30);

        $likes = PostLike::query()
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->count();
        $comments = PostComment::query()
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->count();
        $bookmarks = Bookmark::query()
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->count();
        $shareEvents = $this->eventsInRange($range)
            ->where('event_type', AnalyticsEventTypes::SHARE)
            ->count();

        $relationalEngagements = $likes + $comments + $bookmarks + $shareEvents;

        return [
            'registered_users' => $appUsers,
            'total_users' => User::query()->count(),
            'new_users' => $newUsers,
            'dau' => $dau,
            'wau' => $wau,
            'mau' => $mau,
            'app_opens' => $appOpens,
            'session_rows' => $totalSessions,
            'avg_session_duration_seconds' => round($avgDuration, 1),
            'likes' => $likes,
            'comments' => $comments,
            'bookmark_rows' => $bookmarks,
            'relational_engagements' => $relationalEngagements,
            'tracking_since' => AnalyticsEvent::query()->min(DB::raw('COALESCE(occurred_at, created_at)')),
            'sessions_tracking_since' => AnalyticsSession::query()->min('started_at'),
        ];
    }

    public function activeUsersForDays(int $days): int
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $fromSessions = AnalyticsSession::query()
            ->whereNotNull('user_id')
            ->where('last_activity_at', '>=', $from)
            ->distinct()
            ->count('user_id');

        if ($fromSessions > 0) {
            return $fromSessions;
        }

        return AnalyticsEvent::query()
            ->whereNotNull('user_id')
            ->where(function ($q) use ($from) {
                $q->where('occurred_at', '>=', $from)
                    ->orWhere(function ($inner) use ($from) {
                        $inner->whereNull('occurred_at')->where('created_at', '>=', $from);
                    });
            })
            ->distinct()
            ->count('user_id');
    }

    public function screenBreakdown(AnalyticsDateRange $range, int $limit = 20): Collection
    {
        return $this->eventsInRange($range)
            ->select('screen_name', DB::raw('count(*) as total'), DB::raw('count(distinct user_id) as unique_users'))
            ->where('event_type', AnalyticsEventTypes::SCREEN_VIEW)
            ->whereNotNull('screen_name')
            ->groupBy('screen_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    public function postDetail(int $postId, AnalyticsDateRange $range): array
    {
        $post = Post::query()
            ->with(['author:id,name', 'category:id,name'])
            ->findOrFail($postId);

        $views = $this->eventsInRange($range)
            ->where('post_id', $postId)
            ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
            ->count();
        $uniqueViewers = $this->eventsInRange($range)
            ->where('post_id', $postId)
            ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
        $likes = PostLike::query()
            ->where('post_id', $postId)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->count();
        $comments = PostComment::query()
            ->where('post_id', $postId)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->count();
        $bookmarks = Bookmark::query()
            ->where('post_id', $postId)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->count();
        $shares = $this->eventsInRange($range)
            ->where('post_id', $postId)
            ->where('event_type', AnalyticsEventTypes::SHARE)
            ->count();
        $engagements = $likes + $comments + $bookmarks + $shares;

        $devices = $this->eventsInRange($range)
            ->select('platform', DB::raw('count(*) as total'))
            ->where('post_id', $postId)
            ->groupBy('platform')
            ->pluck('total', 'platform');

        return [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'author' => $post->author?->name,
                'category' => $post->category?->name,
                'publish_date' => optional($post->publish_date)?->toIso8601String(),
                'status' => $post->status,
            ],
            'metrics' => [
                'views' => $views,
                'unique_viewers' => $uniqueViewers,
                'likes' => $likes,
                'comments' => $comments,
                'bookmarks' => $bookmarks,
                'shares' => $shares,
                'engagements' => $engagements,
                'engagement_rate' => $views > 0 ? round(($engagements / $views) * 100, 1) : 0,
            ],
            'devices' => [
                'android' => (int) ($devices['android'] ?? 0),
                'ios' => (int) ($devices['ios'] ?? 0),
                'web' => (int) ($devices['web'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function postEngagers(int $postId, AnalyticsDateRange $range, array $filters = [], int $perPage = 25): array
    {
        $interaction = (string) ($filters['interaction'] ?? 'any');

        $viewerIds = $this->eventsInRange($range)
            ->where('post_id', $postId)
            ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $likeIds = PostLike::query()
            ->where('post_id', $postId)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->pluck('user_id');
        $commentIds = PostComment::query()
            ->where('post_id', $postId)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->pluck('user_id');
        $bookmarkIds = Bookmark::query()
            ->where('post_id', $postId)
            ->where('created_at', '>=', $range->from)
            ->where('created_at', '<=', $range->to)
            ->pluck('user_id');
        $shareIds = $this->eventsInRange($range)
            ->where('post_id', $postId)
            ->where('event_type', AnalyticsEventTypes::SHARE)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $userIds = match ($interaction) {
            'liked' => $likeIds,
            'commented' => $commentIds,
            'bookmarked' => $bookmarkIds,
            'shared' => $shareIds,
            'viewed_only' => $viewerIds->diff($likeIds->merge($commentIds)->merge($bookmarkIds)->merge($shareIds)),
            default => $viewerIds->merge($likeIds)->merge($commentIds)->merge($bookmarkIds)->merge($shareIds)->unique(),
        };

        $paginator = User::query()
            ->whereIn('id', $userIds->filter()->values()->all())
            ->orderBy('name')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (User $user) use ($postId, $range, $likeIds, $commentIds, $bookmarkIds, $shareIds) {
            $views = $this->eventsInRange($range)
                ->where('post_id', $postId)
                ->where('user_id', $user->id)
                ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
                ->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'age' => $this->ageFromBirthday($user->birthday),
                'city' => $user->city,
                'province' => $user->province,
                'region' => $user->region,
                'views' => $views,
                'liked' => $likeIds->contains($user->id),
                'commented' => $commentIds->contains($user->id),
                'bookmarked' => $bookmarkIds->contains($user->id),
                'shared' => $shareIds->contains($user->id),
            ];
        });

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filterUsers(array $filters, AnalyticsDateRange $range, int $perPage = 25): array
    {
        $query = User::query()
            ->where(function ($q) {
                $q->where('role', 'app_user')
                    ->orWhereNotIn('role', ['super_admin', 'editor', 'publisher', 'analytics_viewer']);
            });

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        if (isset($filters['age_min']) || isset($filters['age_max'])) {
            $ageMin = isset($filters['age_min']) ? (int) $filters['age_min'] : 0;
            $ageMax = isset($filters['age_max']) ? (int) $filters['age_max'] : 120;
            $maxBirthday = now()->subYears($ageMin)->endOfDay();
            $minBirthday = now()->subYears($ageMax + 1)->addDay()->startOfDay();
            $query->whereNotNull('birthday')
                ->whereBetween('birthday', [$minBirthday->toDateString(), $maxBirthday->toDateString()]);
        }

        if (! empty($filters['region'])) {
            $query->where('region', $filters['region']);
        }
        if (! empty($filters['province'])) {
            $query->where('province', $filters['province']);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (! empty($filters['registered_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['registered_from'])->startOfDay());
        }
        if (! empty($filters['registered_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['registered_to'])->endOfDay());
        }

        if (($filters['incomplete_profile'] ?? null) === true || ($filters['incomplete_profile'] ?? null) === '1') {
            $query->where(function ($q) {
                $q->whereNull('birthday')
                    ->orWhereNull('nickname')
                    ->orWhereNull('about');
            });
        }

        if (! empty($filters['inactive_days'])) {
            $days = (int) $filters['inactive_days'];
            $cutoff = now()->subDays($days);
            $activeIds = AnalyticsEvent::query()
                ->whereNotNull('user_id')
                ->where(function ($q) use ($cutoff) {
                    $q->where('occurred_at', '>=', $cutoff)
                        ->orWhere(function ($inner) use ($cutoff) {
                            $inner->whereNull('occurred_at')->where('created_at', '>=', $cutoff);
                        });
                })
                ->distinct()
                ->pluck('user_id');
            $query->whereNotIn('id', $activeIds);
        }

        if (! empty($filters['platform'])) {
            $platform = $filters['platform'];
            $query->whereIn('id', function ($sub) use ($platform, $range) {
                $sub->select('user_id')
                    ->from('analytics_events')
                    ->whereNotNull('user_id')
                    ->where('platform', $platform)
                    ->where(function ($q) use ($range) {
                        $q->whereBetween('occurred_at', [$range->from, $range->to])
                            ->orWhere(function ($inner) use ($range) {
                                $inner->whereNull('occurred_at')
                                    ->whereBetween('created_at', [$range->from, $range->to]);
                            });
                    });
            });
        }

        if (! empty($filters['activity'])) {
            $activity = (string) $filters['activity'];
            $eventType = match ($activity) {
                'opened_app' => AnalyticsEventTypes::APP_OPEN,
                'searched' => AnalyticsEventTypes::SEARCH,
                'viewed_post' => AnalyticsEventTypes::POST_VIEW,
                'liked' => AnalyticsEventTypes::POST_LIKE,
                default => null,
            };
            if ($eventType) {
                $query->whereIn('id', function ($sub) use ($eventType, $range) {
                    $sub->select('user_id')
                        ->from('analytics_events')
                        ->whereNotNull('user_id')
                        ->where('event_type', $eventType)
                        ->where(function ($q) use ($range) {
                            $q->whereBetween('occurred_at', [$range->from, $range->to])
                                ->orWhere(function ($inner) use ($range) {
                                    $inner->whereNull('occurred_at')
                                        ->whereBetween('created_at', [$range->from, $range->to]);
                                });
                        });
                });
            }
            if ($activity === 'commented') {
                $query->whereIn('id', function ($sub) use ($range) {
                    $sub->select('user_id')
                        ->from('post_comments')
                        ->whereBetween('created_at', [$range->from, $range->to]);
                });
            }
        }

        if (! empty($filters['post_id'])) {
            $postId = (int) $filters['post_id'];
            $query->whereIn('id', function ($sub) use ($postId, $range) {
                $sub->select('user_id')
                    ->from('analytics_events')
                    ->where('post_id', $postId)
                    ->whereNotNull('user_id')
                    ->where(function ($q) use ($range) {
                        $q->whereBetween('occurred_at', [$range->from, $range->to])
                            ->orWhere(function ($inner) use ($range) {
                                $inner->whereNull('occurred_at')
                                    ->whereBetween('created_at', [$range->from, $range->to]);
                            });
                    });
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        $data = collect($paginator->items())->map(function (User $user) use ($range) {
            $events = AnalyticsEvent::query()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($range) {
                    $q->whereBetween('occurred_at', [$range->from, $range->to])
                        ->orWhere(function ($inner) use ($range) {
                            $inner->whereNull('occurred_at')
                                ->whereBetween('created_at', [$range->from, $range->to]);
                        });
                });

            $lastEvent = AnalyticsEvent::query()
                ->where('user_id', $user->id)
                ->orderByDesc(DB::raw('COALESCE(occurred_at, created_at)'))
                ->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nickname' => $user->nickname,
                'age' => $this->ageFromBirthday($user->birthday),
                'region' => $user->region,
                'province' => $user->province,
                'city' => $user->city,
                'registered_at' => optional($user->created_at)?->toIso8601String(),
                'events_in_range' => (clone $events)->count(),
                'last_active_at' => optional($lastEvent?->occurred_at ?? $lastEvent?->created_at)?->toIso8601String(),
            ];
        });

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function userDetail(int $userId, AnalyticsDateRange $range): array
    {
        $user = User::query()->findOrFail($userId);
        $events = $this->eventsInRange($range)->where('user_id', $userId);
        $sessions = AnalyticsSession::query()
            ->where('user_id', $userId)
            ->where('started_at', '>=', $range->from)
            ->where('started_at', '<=', $range->to);

        $timeline = AnalyticsEvent::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($range) {
                $q->whereBetween('occurred_at', [$range->from, $range->to])
                    ->orWhere(function ($inner) use ($range) {
                        $inner->whereNull('occurred_at')
                            ->whereBetween('created_at', [$range->from, $range->to]);
                    });
            })
            ->with(['post:id,title'])
            ->orderByDesc(DB::raw('COALESCE(occurred_at, created_at)'))
            ->limit(100)
            ->get()
            ->map(fn (AnalyticsEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'platform' => $e->platform,
                'screen_name' => $e->screen_name,
                'post_id' => $e->post_id,
                'post_title' => $e->post?->title,
                'occurred_at' => optional($e->occurred_at ?? $e->created_at)?->toIso8601String(),
            ]);

        $topScreens = (clone $events)
            ->select('screen_name', DB::raw('count(*) as total'))
            ->whereNotNull('screen_name')
            ->groupBy('screen_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nickname' => $user->nickname,
                'age' => $this->ageFromBirthday($user->birthday),
                'region' => $user->region,
                'province' => $user->province,
                'city' => $user->city,
                'registered_at' => optional($user->created_at)?->toIso8601String(),
                'is_active' => (bool) ($user->is_active ?? true),
            ],
            'summary' => [
                'events' => (clone $events)->count(),
                'sessions' => (clone $sessions)->count(),
                'app_opens' => (clone $events)->where('event_type', AnalyticsEventTypes::APP_OPEN)->count(),
                'post_views' => (clone $events)->whereIn('event_type', AnalyticsEventTypes::viewTypes())->count(),
                'avg_session_duration_seconds' => round((float) (clone $sessions)->avg('duration_seconds'), 1),
            ],
            'top_screens' => $topScreens,
            'timeline' => $timeline,
        ];
    }

    public function retentionCohorts(AnalyticsDateRange $range, string $grain = 'week'): array
    {
        $offsets = [1, 3, 7, 14, 30, 60, 90];
        $cohortStart = $range->from->copy()->startOfDay();
        $cohortEnd = $range->to->copy()->endOfDay();

        $users = User::query()
            ->where('created_at', '>=', $cohortStart)
            ->where('created_at', '<=', $cohortEnd)
            ->where(function ($q) {
                $q->where('role', 'app_user')
                    ->orWhereNotIn('role', ['super_admin', 'editor', 'publisher', 'analytics_viewer']);
            })
            ->get(['id', 'created_at']);

        $grouped = $users->groupBy(function (User $user) use ($grain) {
            $created = $user->created_at->copy();

            return $grain === 'month'
                ? $created->startOfMonth()->toDateString()
                : $created->startOfWeek()->toDateString();
        });

        $rows = [];
        foreach ($grouped as $cohortDate => $cohortUsers) {
            $size = $cohortUsers->count();
            $cells = [];
            foreach ($offsets as $day) {
                $matureAt = Carbon::parse($cohortDate)->addDays($day)->endOfDay();
                if ($matureAt->gt(now())) {
                    $cells[(string) $day] = null;
                    continue;
                }

                $retained = 0;
                foreach ($cohortUsers as $user) {
                    $windowStart = $user->created_at->copy()->addDays($day)->startOfDay();
                    $windowEnd = $user->created_at->copy()->addDays($day)->endOfDay();
                    $has = AnalyticsEvent::query()
                        ->where('user_id', $user->id)
                        ->where(function ($q) use ($windowStart, $windowEnd) {
                            $q->whereBetween('occurred_at', [$windowStart, $windowEnd])
                                ->orWhere(function ($inner) use ($windowStart, $windowEnd) {
                                    $inner->whereNull('occurred_at')
                                        ->whereBetween('created_at', [$windowStart, $windowEnd]);
                                });
                        })
                        ->exists();
                    if ($has) {
                        $retained++;
                    }
                }
                $cells[(string) $day] = $size > 0 ? round(($retained / $size) * 100, 1) : 0;
            }

            $rows[] = [
                'cohort' => $cohortDate,
                'size' => $size,
                'retention' => $cells,
            ];
        }

        return [
            'grain' => $grain,
            'offsets' => $offsets,
            'rows' => $rows,
            'method' => 'classic',
            'note' => 'Null cells are immature (not enough calendar time has passed).',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function leaderboard(AnalyticsDateRange $range, string $metric = 'engagements', int $limit = 25, array $filters = []): array
    {
        $limit = max(1, min(100, $limit));
        $weights = $this->engagementWeights();

        $userIdsQuery = AnalyticsEvent::query()
            ->whereNotNull('user_id')
            ->where(function ($q) use ($range) {
                $q->whereBetween('occurred_at', [$range->from, $range->to])
                    ->orWhere(function ($inner) use ($range) {
                        $inner->whereNull('occurred_at')
                            ->whereBetween('created_at', [$range->from, $range->to]);
                    });
            });

        if (! empty($filters['platform'])) {
            $userIdsQuery->where('platform', $filters['platform']);
        }

        $userIds = $userIdsQuery->distinct()->pluck('user_id');

        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $rows = [];

        foreach ($userIds as $userId) {
            $user = $users->get($userId);
            if (! $user) {
                continue;
            }

            $events = AnalyticsEvent::query()
                ->where('user_id', $userId)
                ->where(function ($q) use ($range) {
                    $q->whereBetween('occurred_at', [$range->from, $range->to])
                        ->orWhere(function ($inner) use ($range) {
                            $inner->whereNull('occurred_at')
                                ->whereBetween('created_at', [$range->from, $range->to]);
                        });
                })
                ->select('event_type', DB::raw('count(*) as total'))
                ->groupBy('event_type')
                ->pluck('total', 'event_type');

            $views = (int) ($events[AnalyticsEventTypes::POST_VIEW] ?? 0) + (int) ($events[AnalyticsEventTypes::LEGACY_VIEW] ?? 0);
            $likes = (int) ($events[AnalyticsEventTypes::POST_LIKE] ?? 0);
            $shares = (int) ($events[AnalyticsEventTypes::SHARE] ?? 0);
            $searches = (int) ($events[AnalyticsEventTypes::SEARCH] ?? 0);
            $appOpens = (int) ($events[AnalyticsEventTypes::APP_OPEN] ?? 0);
            $comments = PostComment::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$range->from, $range->to])
                ->count();
            $bookmarks = Bookmark::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$range->from, $range->to])
                ->count();
            $sessions = AnalyticsSession::query()
                ->where('user_id', $userId)
                ->whereBetween('started_at', [$range->from, $range->to])
                ->count();
            $duration = (int) AnalyticsSession::query()
                ->where('user_id', $userId)
                ->whereBetween('started_at', [$range->from, $range->to])
                ->sum('duration_seconds');

            $score = 0;
            foreach ($events as $type => $total) {
                $canonical = AnalyticsEventTypes::canonicalize((string) $type);
                $score += ((int) $total) * (int) ($weights[$canonical] ?? $weights[(string) $type] ?? 0);
            }
            $score += $comments * (int) ($weights['post_comment'] ?? 3);
            $score += $bookmarks * (int) ($weights['bookmark'] ?? 2);

            $metricValue = match ($metric) {
                'views', 'page_views' => $views,
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'downloads' => 0,
                'app_opens' => $appOpens,
                'sessions' => $sessions,
                'duration' => $duration,
                'searches' => $searches,
                'score' => $score,
                default => $likes + $comments + $shares + $bookmarks,
            };

            $rows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'age' => $this->ageFromBirthday($user->birthday),
                'city' => $user->city,
                'province' => $user->province,
                'views' => $views,
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'bookmarks' => $bookmarks,
                'app_opens' => $appOpens,
                'sessions' => $sessions,
                'duration_seconds' => $duration,
                'engagement_score' => $score,
                'metric_value' => $metricValue,
            ];
        }

        usort($rows, fn ($a, $b) => $b['metric_value'] <=> $a['metric_value']);
        $rows = array_slice($rows, 0, $limit);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }

        return [
            'metric' => $metric,
            'weights' => $weights,
            'rows' => $rows,
        ];
    }

    public function geographicDistribution(AnalyticsDateRange $range, string $level = 'province'): array
    {
        $column = match ($level) {
            'region' => 'region',
            'city' => 'city',
            default => 'province',
        };

        $activeIds = AnalyticsEvent::query()
            ->whereNotNull('user_id')
            ->where(function ($q) use ($range) {
                $q->whereBetween('occurred_at', [$range->from, $range->to])
                    ->orWhere(function ($inner) use ($range) {
                        $inner->whereNull('occurred_at')
                            ->whereBetween('created_at', [$range->from, $range->to]);
                    });
            })
            ->distinct()
            ->pluck('user_id');

        $registered = User::query()
            ->select($column.' as location', DB::raw('count(*) as total'))
            ->where(function ($q) {
                $q->where('role', 'app_user')
                    ->orWhereNotIn('role', ['super_admin', 'editor', 'publisher', 'analytics_viewer']);
            })
            ->groupBy($column)
            ->get();

        $rows = [];
        $unknown = 0;
        foreach ($registered as $row) {
            $loc = $row->location ?: null;
            if ($loc === null || $loc === '') {
                $unknown += (int) $row->total;
                continue;
            }
            if ((int) $row->total < 5) {
                // k-anonymity: fold small cells into Unknown.
                $unknown += (int) $row->total;
                continue;
            }
            $active = User::query()
                ->where($column, $loc)
                ->whereIn('id', $activeIds)
                ->count();
            $rows[] = [
                'location' => $loc,
                'registered' => (int) $row->total,
                'active' => $active,
            ];
        }

        usort($rows, fn ($a, $b) => $b['registered'] <=> $a['registered']);

        return [
            'level' => $level,
            'rows' => $rows,
            'unknown' => $unknown,
            'k_anonymity' => 5,
            'has_location_data' => User::query()->whereNotNull($column)->where($column, '!=', '')->exists(),
        ];
    }

    public function funnelReport(string $funnelKey, AnalyticsDateRange $range): array
    {
        $funnel = \App\Models\AnalyticsFunnelDefinition::query()
            ->with('steps')
            ->where('key', $funnelKey)
            ->where('is_active', true)
            ->first();

        if (! $funnel) {
            return [
                'key' => $funnelKey,
                'name' => 'Default content journey',
                'steps' => $this->defaultFunnelSteps($range),
            ];
        }

        $stepsOut = [];
        $previousUsers = null;
        foreach ($funnel->steps as $step) {
            $users = AnalyticsEvent::query()
                ->where('event_type', $step->event_type)
                ->whereNotNull('user_id')
                ->where(function ($q) use ($range) {
                    $q->whereBetween('occurred_at', [$range->from, $range->to])
                        ->orWhere(function ($inner) use ($range) {
                            $inner->whereNull('occurred_at')
                                ->whereBetween('created_at', [$range->from, $range->to]);
                        });
                })
                ->when($step->resource_type, fn ($q) => $q->where('resource_type', $step->resource_type))
                ->distinct()
                ->pluck('user_id');

            if ($previousUsers !== null && $funnel->is_sequential) {
                $users = $users->intersect($previousUsers)->values();
            }

            $count = $users->count();
            $base = $previousUsers === null ? max($count, 1) : max($previousUsers->count(), 1);
            $stepsOut[] = [
                'order' => $step->step_order,
                'name' => $step->name,
                'event_type' => $step->event_type,
                'users' => $count,
                'conversion_from_previous' => $previousUsers === null ? 100 : round(($count / $base) * 100, 1),
                'drop_off' => $previousUsers === null ? 0 : max(0, $previousUsers->count() - $count),
            ];
            $previousUsers = $users;
        }

        return [
            'key' => $funnel->key,
            'name' => $funnel->name,
            'max_window_hours' => $funnel->max_window_hours,
            'is_sequential' => $funnel->is_sequential,
            'steps' => $stepsOut,
        ];
    }

    private function defaultFunnelSteps(AnalyticsDateRange $range): array
    {
        $registered = User::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();
        $loggedIn = AnalyticsEvent::query()
            ->whereNotNull('user_id')
            ->where(function ($q) use ($range) {
                $q->whereBetween('occurred_at', [$range->from, $range->to])
                    ->orWhere(function ($inner) use ($range) {
                        $inner->whereNull('occurred_at')->whereBetween('created_at', [$range->from, $range->to]);
                    });
            })
            ->distinct()
            ->count('user_id');
        $viewed = $this->eventsInRange($range)
            ->whereIn('event_type', AnalyticsEventTypes::viewTypes())
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
        $engaged = $this->eventsInRange($range)
            ->whereIn('event_type', AnalyticsEventTypes::engagementTypes())
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $steps = [
            ['name' => 'Registered', 'users' => $registered],
            ['name' => 'Active (any event)', 'users' => $loggedIn],
            ['name' => 'Viewed content', 'users' => $viewed],
            ['name' => 'Engaged', 'users' => $engaged],
        ];

        $out = [];
        $prev = null;
        foreach ($steps as $i => $step) {
            $count = $step['users'];
            $out[] = [
                'order' => $i + 1,
                'name' => $step['name'],
                'users' => $count,
                'conversion_from_previous' => $prev === null ? 100 : ($prev > 0 ? round(($count / $prev) * 100, 1) : 0),
                'drop_off' => $prev === null ? 0 : max(0, $prev - $count),
            ];
            $prev = $count;
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function engagementWeights(): array
    {
        $rows = \App\Models\AnalyticsEngagementScoreWeight::query()
            ->where('is_active', true)
            ->pluck('points', 'event_key');

        if ($rows->isEmpty()) {
            return [
                AnalyticsEventTypes::APP_OPEN => 1,
                AnalyticsEventTypes::SCREEN_VIEW => 1,
                AnalyticsEventTypes::PAGE_VIEW => 1,
                AnalyticsEventTypes::POST_VIEW => 2,
                AnalyticsEventTypes::POST_LIKE => 2,
                'post_comment' => 3,
                AnalyticsEventTypes::SHARE => 5,
                AnalyticsEventTypes::BOOKMARK => 2,
                AnalyticsEventTypes::SEARCH => 1,
                AnalyticsEventTypes::FEATURE_USE => 2,
            ];
        }

        return $rows->map(fn ($v) => (int) $v)->all();
    }

    private function metricSummaryBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $events = $this->eventsBetween($from, $to)
            ->select('event_type', DB::raw('count(*) as total'))
            ->groupBy('event_type')
            ->get();

        $summary = $this->summarizeMetrics($events);
        $baseQuery = $this->eventsBetween($from, $to);

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

    private function eventsInRange(AnalyticsDateRange $range): Builder
    {
        return $this->eventsBetween($range->from, $range->to);
    }

    private function eventsBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return AnalyticsEvent::query()->where(function ($q) use ($from, $to) {
            $q->where(function ($inner) use ($from, $to) {
                $inner->whereNotNull('analytics_events.occurred_at')
                    ->where('analytics_events.occurred_at', '>=', $from)
                    ->where('analytics_events.occurred_at', '<=', $to);
            })->orWhere(function ($inner) use ($from, $to) {
                $inner->whereNull('analytics_events.occurred_at')
                    ->where('analytics_events.created_at', '>=', $from)
                    ->where('analytics_events.created_at', '<=', $to);
            });
        });
    }

    private function summarizeMetrics(Collection $events): array
    {
        $summary = [
            'views' => 0,
            'bookmarks' => 0,
            'searches' => 0,
            'shares' => 0,
            'likes' => 0,
            'app_opens' => 0,
            'screen_views' => 0,
        ];

        $viewTypes = AnalyticsEventTypes::viewTypes();

        foreach ($events as $event) {
            $type = (string) $event->event_type;
            $canonical = AnalyticsEventTypes::canonicalize($type);
            $total = (int) $event->total;

            if (in_array($type, $viewTypes, true) || in_array($canonical, $viewTypes, true)) {
                $summary['views'] += $total;
            } elseif ($canonical === AnalyticsEventTypes::BOOKMARK || $type === 'bookmark') {
                $summary['bookmarks'] += $total;
            } elseif ($canonical === AnalyticsEventTypes::SEARCH || $type === 'search') {
                $summary['searches'] += $total;
            } elseif ($canonical === AnalyticsEventTypes::SHARE || $type === 'share') {
                $summary['shares'] += $total;
            } elseif ($canonical === AnalyticsEventTypes::POST_LIKE) {
                $summary['likes'] += $total;
            } elseif ($canonical === AnalyticsEventTypes::APP_OPEN) {
                $summary['app_opens'] += $total;
            } elseif ($canonical === AnalyticsEventTypes::SCREEN_VIEW) {
                $summary['screen_views'] += $total;
            }
        }

        return $summary;
    }

    private function ageFromBirthday(mixed $birthday): ?int
    {
        if (! $birthday) {
            return null;
        }
        try {
            return Carbon::parse($birthday)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function hourExpression(): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%H', COALESCE(occurred_at, created_at)) AS INTEGER)"
            : 'HOUR(COALESCE(occurred_at, created_at))';
    }

    private function weekdayExpression(): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%w', COALESCE(occurred_at, created_at)) AS INTEGER)"
            : '(DAYOFWEEK(COALESCE(occurred_at, created_at)) - 1)';
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
}
