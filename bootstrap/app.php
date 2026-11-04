<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->encryptCookies(except: [
            \App\Support\Auth\AdminGateCookie::NAME,
            \App\Support\Auth\AdminRoleCookie::NAME,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'user.active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'password.current' => \App\Http\Middleware\Password\EnsurePasswordIsCurrent::class,
            'login.verified' => \App\Http\Middleware\EnsureLoginVerified::class,
            'admin.page' => \App\Http\Middleware\EnsureAdminPageAccess::class,
            'audit.admin' => \App\Http\Middleware\LogAdminAudit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();
