<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    public const REQUEST_FLAG = 'audit.logged';

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'remember_token',
        'secret',
        'api_key',
        'authorization',
        'db_password',
        'db_root_password',
        'mail_password',
        'fcm_token',
    ];

    private const CATEGORY_MAP = [
        'users' => 'users',
        'posts' => 'posts',
        'notifications' => 'notifications',
        'categories' => 'posts',
        'rehab-centers' => 'rehab-centers',
        'contests' => 'contests',
        'iec-materials' => 'iec-materials',
        'app-translations' => 'app-translations',
        'blocked-words' => 'word-blocking',
        'legal-pages' => 'settings',
        'kid-listo-quotes' => 'settings',
        'app-options' => 'lookups',
        'care-toolkit-questions' => 'care-toolkit',
        'care-checks' => 'care-checks',
        'care-support-resources' => 'care-support',
        'hope-directory' => 'hope-directory',
        'hope-events' => 'hope-events',
        'diary-entries' => 'diary',
        'diary-users' => 'diary',
        'analytics' => 'analytics',
        'inbox' => 'inbox',
        'search' => 'search',
        'dashboard' => 'dashboard',
        'icon-picker' => 'lookups',
        'trainings' => 'trainings',
        'auth' => 'auth',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes, ?Request $request = null): AuditLog
    {
        $request ??= request();
        $actor = $attributes['actor'] ?? $request?->user();

        unset($attributes['actor']);

        $payload = [
            'actor_id' => null,
            'actor_name' => null,
            'actor_email' => null,
            'actor_role' => null,
            'category' => (string) ($attributes['category'] ?? 'system'),
            'action' => (string) ($attributes['action'] ?? 'action'),
            'description' => (string) ($attributes['description'] ?? 'Admin action'),
            'subject_type' => $attributes['subject_type'] ?? null,
            'subject_id' => $attributes['subject_id'] ?? null,
            'subject_label' => $attributes['subject_label'] ?? null,
            'old_values' => $this->sanitize($attributes['old_values'] ?? null),
            'new_values' => $this->sanitize($attributes['new_values'] ?? null),
            'properties' => $this->sanitize($attributes['properties'] ?? null),
            'ip_address' => $attributes['ip_address'] ?? $request?->ip(),
            'user_agent' => $attributes['user_agent'] ?? $request?->userAgent(),
            'created_at' => now(),
        ];

        if ($actor instanceof User) {
            $payload['actor_id'] = $actor->id;
            $payload['actor_name'] = $actor->name;
            $payload['actor_email'] = $actor->email;
            $payload['actor_role'] = $actor->role;
        } elseif (is_array($actor)) {
            $payload['actor_id'] = $actor['id'] ?? null;
            $payload['actor_name'] = $actor['name'] ?? null;
            $payload['actor_email'] = $actor['email'] ?? null;
            $payload['actor_role'] = $actor['role'] ?? null;
        }

        if ($request instanceof Request) {
            $request->attributes->set(self::REQUEST_FLAG, true);
        }

        return AuditLog::query()->create($payload);
    }

    public function fromRequest(Request $request, Response $response): ?AuditLog
    {
        if ($request->attributes->get(self::REQUEST_FLAG) === true) {
            return null;
        }

        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        if ($response->getStatusCode() >= 500) {
            return null;
        }

        $routeName = (string) $request->route()?->getName();
        if (str_contains($routeName, 'audit-logs') || str_contains($request->path(), '/admin/audit-logs')) {
            return null;
        }

        // Only audit admin API surface (plus auth mutations handled elsewhere when needed).
        if (! str_starts_with($request->path(), 'api/v1/admin/')
            && ! str_starts_with($request->path(), 'api/v1/auth/')) {
            return null;
        }

        // Auth GETs are not mutating; auth POST login/logout handled explicitly when desired.
        if (str_starts_with($request->path(), 'api/v1/auth/')
            && ! in_array($routeName, [
                'api.v1.auth.logout',
                'api.v1.auth.password.update',
                'api.v1.auth.profile.update',
                'api.v1.auth.profile.update.post',
                'api.v1.auth.onboarding.update',
                'api.v1.auth.onboarding.update.post',
            ], true)) {
            return null;
        }

        [$category, $action, $description] = $this->describeRequest($request, $response);
        [$subjectType, $subjectId, $subjectLabel] = $this->resolveSubject($request);

        return $this->record([
            'category' => $category,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'properties' => [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'route' => $routeName !== '' ? $routeName : null,
                'status' => $response->getStatusCode(),
                'input' => $this->sanitize($request->except(self::SENSITIVE_KEYS)),
            ],
        ], $request);
    }

    public function markLogged(?Request $request = null): void
    {
        ($request ?? request())?->attributes->set(self::REQUEST_FLAG, true);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function describeRequest(Request $request, Response $response): array
    {
        $path = trim(Str::after($request->path(), 'api/v1/'), '/');
        $segments = explode('/', $path);
        $area = $segments[0] === 'admin' ? ($segments[1] ?? 'admin') : ($segments[0] ?? 'system');
        $category = self::CATEGORY_MAP[$area] ?? Str::slug($area, '-');

        $method = strtoupper($request->method());
        $action = match ($method) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'action',
        };

        $routeName = (string) $request->route()?->getName();
        if (str_ends_with($routeName, '.role') || str_contains($routeName, 'force-password')) {
            $action = str_contains($routeName, 'force-password') ? 'force_password' : 'role_changed';
        } elseif (str_contains($routeName, 'suspend')) {
            $action = 'suspended';
        } elseif (str_contains($routeName, 'activate')) {
            $action = 'activated';
        } elseif (str_contains($routeName, 'archive')) {
            $action = 'archived';
        } elseif (str_contains($routeName, 'publish')) {
            $action = 'published';
        } elseif (str_contains($routeName, 'reject')) {
            $action = 'rejected';
        } elseif (str_contains($routeName, 'schedule')) {
            $action = 'scheduled';
        } elseif (str_contains($routeName, 'submit')) {
            $action = 'submitted';
        } elseif (str_contains($routeName, 'send') || str_contains($routeName, 'notifications.send')) {
            $action = 'sent';
        } elseif (str_contains($routeName, 'password')) {
            $action = 'password_changed';
            $category = 'auth';
        } elseif (str_contains($routeName, 'logout')) {
            $action = 'logout';
            $category = 'auth';
        }

        $label = Str::headline(str_replace('-', ' ', $category));
        $verb = match ($action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'suspended' => 'suspended',
            'activated' => 'enabled',
            'force_password' => 'forced password change for',
            'role_changed' => 'changed role for',
            'archived' => 'archived',
            'published' => 'published',
            'rejected' => 'rejected',
            'scheduled' => 'scheduled',
            'submitted' => 'submitted',
            'sent' => 'sent',
            'password_changed' => 'changed password',
            'logout' => 'signed out',
            default => 'performed an action on',
        };

        $status = $response->getStatusCode();
        $suffix = $status >= 400 ? " (HTTP {$status})" : '';

        if (in_array($action, ['password_changed', 'logout'], true)) {
            $description = ucfirst($verb).$suffix;
        } else {
            $description = "{$label}: {$verb} record{$suffix}";
        }

        return [$category, $action, $description];
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: ?string}
     */
    private function resolveSubject(Request $request): array
    {
        $route = $request->route();
        if ($route === null) {
            return [null, null, null];
        }

        foreach ($route->parameters() as $key => $value) {
            if ($value instanceof Model) {
                $label = $value->getAttribute('name')
                    ?? $value->getAttribute('title')
                    ?? $value->getAttribute('email')
                    ?? $value->getAttribute('word')
                    ?? '#'.$value->getKey();

                return [
                    $value->getMorphClass(),
                    (int) $value->getKey(),
                    is_scalar($label) ? (string) $label : '#'.$value->getKey(),
                ];
            }

            if (is_numeric($value) && ! in_array($key, ['page', 'per_page'], true)) {
                return [Str::studly(Str::singular((string) $key)), (int) $value, '#'.$value];
            }
        }

        return [null, null, null];
    }

    private function sanitize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $keyString = is_string($key) ? strtolower($key) : (string) $key;

            if (in_array($keyString, self::SENSITIVE_KEYS, true)
                || str_contains($keyString, 'password')
                || str_ends_with($keyString, '_token')) {
                $clean[$key] = '[redacted]';
                continue;
            }

            if (is_array($item)) {
                $clean[$key] = $this->sanitize($item);
                continue;
            }

            if (is_string($item) && strlen($item) > 2000) {
                $clean[$key] = substr($item, 0, 2000).'…';
                continue;
            }

            $clean[$key] = $item;
        }

        return $clean;
    }
}
