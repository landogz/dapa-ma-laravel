<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'Forbidden. You do not have the required role.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Super admin always allowed
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Forbidden. You do not have the required role.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
