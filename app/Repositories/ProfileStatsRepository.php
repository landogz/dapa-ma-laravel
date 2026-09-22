<?php

namespace App\Repositories;

use App\Models\AnalyticsEvent;
use App\Models\Bookmark;
use App\Models\ContestEntry;
use App\Models\DiaryEntry;
use App\Models\Post;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfileStatsRepository
{
    /** @var list<string> */
    public const LESSON_CATEGORY_SLUGS = [
        'prevention',
        'rehabilitation',
        'drug-effects',
        'iec',
    ];

    /** @var list<string> */
    public const ARTICLE_CATEGORY_SLUGS = [
        'news',
        'legal',
    ];

    public function bookmarksCount(User $user): int
    {
        return Bookmark::query()->where('user_id', $user->id)->count();
    }

    public function reviewsCount(User $user): int
    {
        return Review::query()->where('user_id', $user->id)->count();
    }

    public function distinctPostViewsAll(User $user): int
    {
        return (int) AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['post_view', 'view', 'lesson_complete', 'article_read'])
            ->whereNotNull('post_id')
            ->selectRaw('COUNT(DISTINCT post_id) as aggregate')
            ->value('aggregate');
    }

    public function distinctPostViewsByCategorySlugs(User $user, array $slugs): int
    {
        if ($slugs === []) {
            return 0;
        }

        return (int) AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['post_view', 'view', 'lesson_complete', 'article_read'])
            ->whereNotNull('post_id')
            ->whereHas('post.category', fn ($q) => $q->whereIn('slug', $slugs))
            ->selectRaw('COUNT(DISTINCT post_id) as aggregate')
            ->value('aggregate');
    }

    public function eventsJoinedCount(User $user): int
    {
        $trainingJoins = (int) AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['event_join', 'training_join'])
            ->where('resource_type', 'training')
            ->whereNotNull('resource_id')
            ->selectRaw('COUNT(DISTINCT resource_id) as aggregate')
            ->value('aggregate');

        $contestEntries = ContestEntry::query()
            ->where('user_id', $user->id)
            ->count();

        return (int) $trainingJoins + $contestEntries;
    }

    public function iecLessonsCount(User $user): int
    {
        return (int) AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'lesson_complete')
            ->where('resource_type', 'iec_material')
            ->whereNotNull('resource_id')
            ->selectRaw('COUNT(DISTINCT resource_id) as aggregate')
            ->value('aggregate');
    }

    public function dayStreak(User $user): int
    {
        $dates = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('entry_date')
            ->pluck('entry_date')
            ->map(function ($date) {
                if ($date instanceof Carbon) {
                    return $date->toDateString();
                }

                return Carbon::parse((string) $date)->toDateString();
            })
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $today = Carbon::today();
        $cursor = $today->toDateString();
        $latest = $dates->first();

        if ($latest !== $cursor && $latest !== $today->copy()->subDay()->toDateString()) {
            return 0;
        }

        if ($latest !== $cursor) {
            $cursor = $today->copy()->subDay()->toDateString();
        }

        $streak = 0;
        $dateSet = $dates->flip();

        while ($dateSet->has($cursor)) {
            $streak++;
            $cursor = Carbon::parse($cursor)->subDay()->toDateString();
        }

        return $streak;
    }

    public function recentPostsAll(User $user, int $limit = 30): Collection
    {
        $postIds = AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['post_view', 'view', 'lesson_complete', 'article_read'])
            ->whereNotNull('post_id')
            ->orderByDesc('created_at')
            ->pluck('post_id')
            ->unique()
            ->take($limit)
            ->values();

        if ($postIds->isEmpty()) {
            return collect();
        }

        $posts = Post::query()
            ->with('category')
            ->whereIn('id', $postIds)
            ->get()
            ->keyBy('id');

        return $postIds
            ->map(fn ($id) => $posts->get($id))
            ->filter()
            ->values();
    }

    public function recentPostsForCategories(User $user, array $slugs, int $limit = 30): Collection
    {
        $postIds = AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['post_view', 'view', 'lesson_complete', 'article_read'])
            ->whereNotNull('post_id')
            ->whereHas('post.category', fn ($q) => $q->whereIn('slug', $slugs))
            ->orderByDesc('created_at')
            ->pluck('post_id')
            ->unique()
            ->take($limit)
            ->values();

        if ($postIds->isEmpty()) {
            return collect();
        }

        $posts = Post::query()
            ->with('category')
            ->whereIn('id', $postIds)
            ->get()
            ->keyBy('id');

        return $postIds
            ->map(fn ($id) => $posts->get($id))
            ->filter()
            ->values();
    }

    public function recentEvents(User $user, int $limit = 30): Collection
    {
        $trainingEvents = AnalyticsEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event_type', ['event_join', 'training_join'])
            ->where('resource_type', 'training')
            ->whereNotNull('resource_id')
            ->orderByDesc('created_at')
            ->get(['resource_id', 'created_at']);

        $trainings = DB::table('trainings')
            ->whereIn('id', $trainingEvents->pluck('resource_id')->unique())
            ->get(['id', 'title', 'category', 'region'])
            ->keyBy('id');

        $fromTrainings = $trainingEvents
            ->unique('resource_id')
            ->map(function (AnalyticsEvent $event) use ($trainings) {
                $training = $trainings->get($event->resource_id);
                if (! $training) {
                    return null;
                }

                return [
                    'id' => (int) $training->id,
                    'type' => 'training',
                    'title' => $training->title,
                    'subtitle' => trim(($training->category ?? '').' · '.($training->region ?? ''), ' ·'),
                    'status' => 'joined',
                    'occurred_at' => $event->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values();

        $fromContests = ContestEntry::query()
            ->with('contest:id,title')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ContestEntry $entry) => [
                'id' => $entry->id,
                'type' => 'contest',
                'title' => $entry->title ?: ($entry->contest?->title ?? 'Contest entry'),
                'subtitle' => $entry->contest?->title,
                'status' => $entry->status,
                'occurred_at' => $entry->created_at?->toIso8601String(),
            ]);

        return $fromTrainings
            ->concat($fromContests)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values();
    }

    public function contestEntries(User $user): Collection
    {
        return ContestEntry::query()
            ->with('contest:id,title')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function certificates(User $user): Collection
    {
        return ContestEntry::query()
            ->with('contest:id,title')
            ->where('user_id', $user->id)
            ->where('status', 'winner')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('created_at')
            ->get();
    }
}
