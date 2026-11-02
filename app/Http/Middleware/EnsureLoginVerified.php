<?php

namespace App\Http\Middleware;

use App\Services\Auth\AccessTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginVerified
{
    private const ALLOWED_ROUTE_NAMES = [
        'api.v1.auth.logout',
        'api.v1.auth.login-code',
        'api.v1.auth.verify-login-code',
    ];

    public function __construct(
        private readonly AccessTokenService $accessTokenService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->accessTokenService->isPending($user)) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        return response()->json([
            'status' => false,
            'message' => 'Enter the 6-digit email code to finish signing in.',
            'data' => [
                'requires_verification' => true,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
