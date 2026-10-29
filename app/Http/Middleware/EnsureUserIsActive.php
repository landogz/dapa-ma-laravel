<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    private const ALLOWED_ROUTE_NAMES = [
        'api.v1.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->is_active !== false) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        $user->currentAccessToken()?->delete();

        return response()->json([
            'status' => false,
            'message' => 'Your account has been suspended. Contact an administrator.',
            'errors' => [
                'account' => ['Your account has been suspended.'],
            ],
            'data' => [
                'is_active' => false,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
