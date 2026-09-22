<?php

namespace App\Services;

use App\Models\ContestEntry;
use App\Models\Post;
use App\Models\User;
use App\Repositories\ProfileStatsRepository;

class ProfileStatsService
{
    public function __construct(
        private readonly ProfileStatsRepository $repository,
        private readonly ProfileService $profileService,
    ) {
    }

    public function summary(User $user): array
    {
        $lessons = $this->repository->distinctPostViewsByCategorySlugs(
            $user,
            ProfileStatsRepository::LESSON_CATEGORY_SLUGS,
        ) + $this->repository->iecLessonsCount($user);
        $articles = $this->repository->distinctPostViewsAll($user);
        $events = $this->repository->eventsJoinedCount($user);
        $streak = $this->repository->dayStreak($user);
        $bookmarks = $this->repository->bookmarksCount($user);
        $reviews = $this->repository->reviewsCount($user);
        $certificates = $this->repository->certificates($user)
            ->map(fn (ContestEntry $entry) => $this->formatCertificate($entry))
            ->values()
            ->all();
        $gains = $this->repository->contestEntries($user)
            ->map(fn (ContestEntry $entry) => $this->formatGain($entry))
            ->values()
            ->all();

        $stats = [
            'lessons_completed' => $lessons,
            'articles_read' => $articles,
            'events_joined' => $events,
            'day_streak' => $streak,
            'bookmarks_count' => $bookmarks,
            'reviews_count' => $reviews,
        ];

        $badges = $this->buildBadges($stats, count($certificates) > 0);

        $userPayload = $this->profileService->formatUser($user);
        $userPayload['created_at'] = $user->created_at?->toIso8601String();
        $userPayload['is_champion'] = collect($badges)->contains(
            fn (array $badge) => ($badge['key'] ?? '') === 'dape_champion' && ($badge['earned'] ?? false)
        );

        return [
            'user' => $userPayload,
            'stats' => $stats,
            'badges' => $badges,
            'certificates' => $certificates,
            'gains' => $gains,
            'activity' => [
                'lessons' => $this->formatPosts(
                    $this->repository->recentPostsForCategories(
                        $user,
                        ProfileStatsRepository::LESSON_CATEGORY_SLUGS,
                    )
                ),
                'articles' => $this->formatPosts(
                    $this->repository->recentPostsAll($user)
                ),
                'events' => $this->repository->recentEvents($user)->all(),
            ],
        ];
    }

    /**
     * @param  array{
     *   lessons_completed:int,
     *   articles_read:int,
     *   events_joined:int,
     *   day_streak:int,
     *   bookmarks_count:int,
     *   reviews_count:int
     * }  $stats
     * @return list<array<string, mixed>>
     */
    private function buildBadges(array $stats, bool $hasWinnerCertificate): array
    {
        return [
            [
                'key' => 'healthy_decision_maker',
                'title' => 'Healthy Decision Maker',
                'description' => 'Complete a prevention or recovery lesson.',
                'icon' => 'search',
                'color' => '#F59E0B',
                'earned' => $stats['lessons_completed'] >= 1 || $stats['articles_read'] >= 1,
            ],
            [
                'key' => 'empowered_peer',
                'title' => 'Empowered Peer',
                'description' => 'Save a resource or leave a review for others.',
                'icon' => 'handshake',
                'color' => '#EF4444',
                'earned' => $stats['bookmarks_count'] >= 1 || $stats['reviews_count'] >= 1,
            ],
            [
                'key' => 'stress_buster',
                'title' => 'Stress Buster',
                'description' => 'Keep a diary streak going.',
                'icon' => 'bolt',
                'color' => '#22C55E',
                'earned' => $stats['day_streak'] >= 1,
            ],
            [
                'key' => 'dape_champion',
                'title' => 'DAPE Champion',
                'description' => 'Win a contest or build strong learning habits.',
                'icon' => 'trophy',
                'color' => '#7C3AED',
                'earned' => $hasWinnerCertificate
                    || ($stats['lessons_completed'] >= 3 && $stats['day_streak'] >= 3)
                    || ($stats['events_joined'] >= 1 && $stats['articles_read'] >= 3),
            ],
        ];
    }

    private function formatCertificate(ContestEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title ?: 'Contest Winner',
            'contest_title' => $entry->contest?->title,
            'status' => $entry->status,
            'earned_at' => ($entry->reviewed_at ?? $entry->created_at)?->toIso8601String(),
            'media_url' => $entry->media_url
                ?? $entry->poster_image_url
                ?? $entry->cover_image_url
                ?? $entry->video_url,
        ];
    }

    private function formatGain(ContestEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title ?: ($entry->contest?->title ?? 'Contest entry'),
            'contest_title' => $entry->contest?->title,
            'status' => $entry->status,
            'entry_type' => $entry->entry_type,
            'submitted_at' => $entry->created_at?->toIso8601String(),
            'media_url' => $entry->media_url
                ?? $entry->poster_image_url
                ?? $entry->cover_image_url
                ?? $entry->thumbnail_url
                ?? $entry->video_url,
        ];
    }

    private function formatPosts($posts): array
    {
        return collect($posts)->map(function (Post $post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'category' => $post->category?->name,
                'category_slug' => $post->category?->slug,
                'media_url' => $post->media_url,
            ];
        })->values()->all();
    }
}
