<?php

namespace App\Support\Auth;

use Symfony\Component\HttpFoundation\Cookie;

final class AdminGateCookie
{
    public const NAME = 'dape_admin_gate';

    public static function make(): Cookie
    {
        return cookie(
            self::NAME,
            '1',
            60 * 24 * 30,
            '/',
            null,
            app()->environment('production'),
            true,
            true,
            'lax',
        );
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::NAME);
    }
}
