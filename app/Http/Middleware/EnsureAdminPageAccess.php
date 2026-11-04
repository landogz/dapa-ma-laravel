<?php

namespace App\Http\Middleware;

use App\Support\Auth\AdminGateCookie;
use App\Support\Auth\AdminRoleCookie;
use App\Support\Auth\AdminRoleSections;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = $request->cookies->get(AdminGateCookie::NAME)
            ?? $request->cookie(AdminGateCookie::NAME);

        if ($gate !== '1') {
            return redirect('/admin/login');
        }

        $role = $request->cookies->get(AdminRoleCookie::NAME)
            ?? $request->cookie(AdminRoleCookie::NAME);

        // Gate without role (legacy session): allow Blade through; SPA + /auth/me set the role cookie.
        if (! is_string($role) || $role === '' || ! array_key_exists($role, AdminRoleSections::ROLE_SECTIONS)) {
            return $next($request);
        }

        $section = AdminRoleSections::sectionForPath($request->getPathInfo());

        if (! AdminRoleSections::canAccess($role, $section)) {
            return redirect(AdminRoleSections::fallbackPath($role));
        }

        return $next($request);
    }
}
