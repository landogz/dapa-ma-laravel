<?php

namespace App\Http\Middleware\Password;

use App\Services\Password\PasswordService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsCurrent
{
    private const ALLOWED_ROUTE_NAMES = [
        'api.v1.auth.logout',
        'api.v1.auth.me',
        'api.v1.auth.password.update',
        'api.v1.auth.login-code',
        'api.v1.auth.verify-login-code',
    ];

    public function __construct(
        private readonly PasswordService $passwordService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->passwordService->requiresChange($user)) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        $expired = $this->passwordService->isExpired($user);

        return response()->json([
            'status' => false,
            'message' => $expired
                ? 'Your password has expired. Set a new password to continue.'
                : 'You must set a new password before continuing.',
            'errors' => [
                'password' => [
                    $expired
                        ? 'Your password has expired. Set a new password to continue.'
                        : 'You must set a new password before continuing.',
                ],
            ],
            'data' => [
                'must_change_password' => true,
                'password_expired' => $expired,
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
