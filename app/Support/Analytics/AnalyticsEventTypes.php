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

    public const IEC_SHARE = 'iec_share';

    public const IEC_DOWNLOAD = 'iec_download';

    public const EVENT_VIEW = 'event_view';

    public const EVENT_SHARE = 'event_share';

    public const CONTEST_VIEW = 'contest_view';

    public const CONTEST_SHARE = 'contest_share';

    public const TRAINING_VIEW = 'training_view';

    public const REHAB_VIEW = 'rehab_view';

    public const REHAB_CALL = 'rehab_call';

    public const REHAB_DIRECTIONS = 'rehab_directions';

    public const HOPE_ORG_VIEW = 'hope_org_view';

    public const JOURNAL_CREATE = 'journal_create';

    public const CARE_CHECK_SUBMIT = 'care_check_submit';

    public const MOOD_CHECKIN = 'mood_checkin';

    public const CALM_COMPLETE = 'calm_complete';

    public const COLOR_FUN_OPEN = 'color_fun_open';

    public const COLOR_FUN_COMPLETE = 'color_fun_complete';

    public const CONTEST_SUBMIT = 'contest_submit';

    public const EVENT_REGISTER = 'event_register';

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
    public static function shareTypes(): array
    {
        return [self::SHARE, self::IEC_SHARE, self::EVENT_SHARE, self::CONTEST_SHARE];
    }

    /**
     * @return list<string>
     */
    public static function downloadTypes(): array
    {
        return [self::IEC_DOWNLOAD];
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
            self::IEC_SHARE,
            self::IEC_DOWNLOAD,
            self::EVENT_SHARE,
            self::CONTEST_SHARE,
            self::REVIEW,
            self::CONTEST_SUBMIT,
            self::EVENT_REGISTER,
            self::REHAB_CALL,
            self::REHAB_DIRECTIONS,
        ];
    }

    /**
     * Care-tool and help-feature counts (no item body).
     *
     * @return list<string>
     */
    public static function careToolTypes(): array
    {
        return [
            self::JOURNAL_CREATE,
            self::CARE_CHECK_SUBMIT,
            self::MOOD_CHECKIN,
            self::CALM_COMPLETE,
            self::COLOR_FUN_OPEN,
            self::COLOR_FUN_COMPLETE,
            self::NOTIFICATION_OPEN,
            self::CONTEST_SUBMIT,
            self::FEATURE_USE,
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
            self::IEC_SHARE,
            self::IEC_DOWNLOAD,
            self::EVENT_VIEW,
            self::EVENT_SHARE,
            self::CONTEST_VIEW,
            self::CONTEST_SHARE,
            self::TRAINING_VIEW,
            self::REHAB_VIEW,
            self::REHAB_CALL,
            self::REHAB_DIRECTIONS,
            self::HOPE_ORG_VIEW,
            self::JOURNAL_CREATE,
            self::CARE_CHECK_SUBMIT,
            self::MOOD_CHECKIN,
            self::CALM_COMPLETE,
            self::COLOR_FUN_OPEN,
            self::COLOR_FUN_COMPLETE,
            self::CONTEST_SUBMIT,
            self::EVENT_REGISTER,
            self::LEGACY_VIEW,
            self::LEGACY_LESSON_COMPLETE,
            self::LEGACY_TRAINING_JOIN,
        ];
    }
}
