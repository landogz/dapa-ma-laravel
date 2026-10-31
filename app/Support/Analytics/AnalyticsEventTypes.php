<?php

namespace App\Support\Analytics;

/**
 * Canonical first-party analytics event catalog.
 * Keep mobile / web emitters aligned with these names.
 */
final class AnalyticsEventTypes
{
    public const APP_OPEN = 'app_open';

    public const SESSION_START = 'session_start';

    public const SCREEN_VIEW = 'screen_view';

    public const PAGE_VIEW = 'page_view';

    public const POST_VIEW = 'post_view';

    public const POST_LIKE = 'post_like';

    public const POST_COMMENT = 'post_comment';

    public const BOOKMARK = 'bookmark';

    public const SHARE = 'share';

    public const SEARCH = 'search';

    public const REVIEW = 'review';

    public const NOTIFICATION_OPEN = 'notification_open';

    public const FEATURE_USE = 'feature_use';

    public const IEC_VIEW = 'iec_view';

    public const TRAINING_VIEW = 'training_view';

    /** Legacy aliases still accepted from older app builds. */
    public const LEGACY_VIEW = 'view';

    public const LEGACY_LESSON_COMPLETE = 'lesson_complete';

    public const LEGACY_TRAINING_JOIN = 'training_join';

    /**
     * Event types counted as content views (exact match only).
     *
     * @return list<string>
     */
    public static function viewTypes(): array
    {
        return [self::POST_VIEW, self::LEGACY_VIEW, self::PAGE_VIEW];
    }

    /**
     * @return list<string>
     */
    public static function engagementTypes(): array
    {
        return [
            self::POST_LIKE,
            self::POST_COMMENT,
            self::BOOKMARK,
            self::SHARE,
            self::REVIEW,
        ];
    }

    /**
     * Normalize legacy event names to canonical ones for reporting buckets.
     */
    public static function canonicalize(string $eventType): string
    {
        return match ($eventType) {
            self::LEGACY_VIEW => self::POST_VIEW,
            self::LEGACY_LESSON_COMPLETE => self::IEC_VIEW,
            self::LEGACY_TRAINING_JOIN => self::TRAINING_VIEW,
            default => $eventType,
        };
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::APP_OPEN,
            self::SESSION_START,
            self::SCREEN_VIEW,
            self::PAGE_VIEW,
            self::POST_VIEW,
            self::POST_LIKE,
            self::POST_COMMENT,
            self::BOOKMARK,
            self::SHARE,
            self::SEARCH,
            self::REVIEW,
            self::NOTIFICATION_OPEN,
            self::FEATURE_USE,
            self::IEC_VIEW,
            self::TRAINING_VIEW,
            self::LEGACY_VIEW,
            self::LEGACY_LESSON_COMPLETE,
            self::LEGACY_TRAINING_JOIN,
        ];
    }
}
