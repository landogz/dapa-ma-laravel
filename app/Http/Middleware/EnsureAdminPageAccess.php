<?php

namespace App\Http\Middleware;

use App\Support\Auth\AdminGateCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = $request->cookies->get(AdminGateCookie::NAME)
            ?? $request->cookie(AdminGateCookie::NAME);

        if ($gate === '1') {
            return $next($request);
        }

        return redirect('/admin/login');
    }
}
