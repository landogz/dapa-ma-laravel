<?php

namespace App\Support\Auth;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Signed cookie storing the staff role for Blade admin page gating.
 * Companion to AdminGateCookie (logged-in gate only).
 */
final class AdminRoleCookie
{
    public const NAME = 'dape_admin_role';

    public static function make(string $role): Cookie
    {
        return cookie(
            self::NAME,
            $role,
            60 * 24 * 30,
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            'lax',
        );
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::NAME);
    }
}
