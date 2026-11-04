<?php

namespace App\Support\Auth;

/**
 * Staff role → admin section map (Blade pages + SPA nav).
 * Keep in sync with resources/js/modules/admin-dashboard.js ADMIN_ROLE_SECTIONS.
 */
final class AdminRoleSections
{
    public const ANALYTICS_METRIC_SECTIONS = [
        'analytics-views',
        'analytics-bookmarks',
        'analytics-shares',
        'analytics-engagement',
    ];

    /** @var array<string, list<string>> */
    public const ROLE_SECTIONS = [
        'super_admin' => [
            'posts',
            'rehab-centers',
            'contests',
            'iec-materials',
            'sticker-library',
            'color-fun-library',
            'app-translations',
            'notifications',
            'analytics',
            'analytics-views',
            'analytics-bookmarks',
            'analytics-shares',
            'analytics-engagement',
            'users',
            'diary',
            'care-toolkit',
            'care-checks',
            'care-support',
            'hope-directory',
            'hope-events',
            'settings',
            'lookups',
            'word-blocking',
            'audit-trail',
        ],
        'editor' => [
            'posts',
            'rehab-centers',
            'contests',
            'iec-materials',
            'sticker-library',
            'color-fun-library',
            'app-translations',
            'notifications',
            'care-toolkit',
            'care-support',
            'hope-directory',
            'hope-events',
            'settings',
            'lookups',
            'word-blocking',
        ],
        'publisher' => [
            'posts',
            'rehab-centers',
            'contests',
            'iec-materials',
            'sticker-library',
            'color-fun-library',
            'app-translations',
            'notifications',
            'care-toolkit',
            'care-support',
            'hope-directory',
            'hope-events',
            'settings',
            'lookups',
            'word-blocking',
        ],
        'analytics_viewer' => [
            'analytics',
            'analytics-views',
            'analytics-bookmarks',
            'analytics-shares',
            'analytics-engagement',
        ],
    ];

    /** activePage (web route) → required section */
    public const PAGE_SECTIONS = [
        'dashboard' => null,
        'profile' => null,
        'posts' => 'posts',
        'rehab-centers' => 'rehab-centers',
        'contests' => 'contests',
        'iec-materials' => 'iec-materials',
        'sticker-library' => 'sticker-library',
        'color-fun-library' => 'color-fun-library',
        'app-translations' => 'app-translations',
        'notifications' => 'notifications',
        'analytics' => 'analytics',
        'users' => 'users',
        'diary' => 'diary',
        'care-toolkit' => 'care-toolkit',
        'care-checks' => 'care-checks',
        'care-support' => 'care-support',
        'hope-directory' => 'hope-directory',
        'hope-events' => 'hope-events',
        'settings' => 'settings',
        'lookups' => 'lookups',
        'word-blocking' => 'word-blocking',
        'audit-trail' => 'audit-trail',
    ];

    /**
     * @return list<string>
     */
    public static function forRole(?string $role): array
    {
        return self::ROLE_SECTIONS[$role] ?? [];
    }

    public static function canAccess(?string $role, ?string $section): bool
    {
        if ($section === null || $section === '') {
            return true;
        }

        return in_array($section, self::forRole($role), true);
    }

    public static function sectionForPath(string $path): ?string
    {
        $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        if (! str_starts_with($path, '/admin')) {
            return null;
        }

        if ($path === '/admin' || $path === '/admin/' || $path === '/admin/dashboard') {
            return null;
        }

        if ($path === '/admin/profile') {
            return null;
        }

        foreach (self::PAGE_SECTIONS as $page => $section) {
            if ($page === 'dashboard' || $page === 'profile') {
                continue;
            }

            if ($path === "/admin/{$page}" || str_starts_with($path, "/admin/{$page}/")) {
                return $section;
            }
        }

        return null;
    }

    public static function fallbackPath(?string $role): string
    {
        $sections = self::forRole($role);

        if (in_array('analytics', $sections, true) && count($sections) <= 5) {
            return '/admin/analytics';
        }

        return '/admin/dashboard';
    }
}
