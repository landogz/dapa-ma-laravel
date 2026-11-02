<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;

final class DemoLogin
{
    /**
     * Demo shortcuts stay off in production. Local / localhost always shows them.
     *
     * @return list<array{label: string, email: string, password: string, role: string}>
     */
    public static function accountsFor(Request $request): array
    {
        return self::shouldShow($request)
            ? array_values(config('dape.demo_login_accounts', []))
            : [];
    }

    public static function shouldShow(Request $request): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        if (filter_var(config('dape.show_demo_login'), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $host = strtolower($request->getHost());

        return app()->environment('local')
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
