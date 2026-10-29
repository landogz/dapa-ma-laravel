<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogAdminAudit
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->auditLogger->fromRequest($request, $response);
        } catch (Throwable) {
            // Never block the primary request if audit persistence fails.
        }

        return $response;
    }
}
