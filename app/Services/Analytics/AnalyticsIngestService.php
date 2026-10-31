<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Support\Analytics\AnalyticsEventTypes;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AnalyticsIngestService
{
    public const MAX_BATCH = 50;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{recorded: int, session_id: ?string}
     */
    public function ingestOne(array $payload, ?int $userId, ?string $sessionId): array
    {
        $result = $this->ingestMany([$payload], $userId, $sessionId);

        return [
            'recorded' => $result['recorded'],
            'session_id' => $result['session_id'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{recorded: int, session_id: ?string}
     */
    public function ingestMany(array $events, ?int $userId, ?string $sessionId): array
    {
        $recorded = 0;
        $resolvedSession = $sessionId;

        DB::transaction(function () use ($events, $userId, &$sessionId, &$recorded, &$resolvedSession): void {
            foreach ($events as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $eventType = trim((string) ($raw['event_type'] ?? ''));
                if ($eventType === '') {
                    continue;
                }

                $occurredAt = $this->resolveOccurredAt($raw['occurred_at'] ?? null);
                $eventSession = $this->normalizeSessionId($raw['session_id'] ?? $sessionId);
                $resolvedSession = $eventSession ?? $resolvedSession;

                $screen = $this->nullableString($raw['screen_name'] ?? null, 120);
                $platform = $this->nullableString($raw['platform'] ?? null, 20);

                AnalyticsEvent::query()->create([
                    'event_type' => AnalyticsEventTypes::canonicalize($eventType) === $eventType
                        ? $eventType
                        : $eventType, // keep original wire name for history; canonicalize in reports
                    'post_id' => $this->nullableInt($raw['post_id'] ?? null),
                    'resource_type' => $this->nullableString($raw['resource_type'] ?? null, 50),
                    'resource_id' => $this->nullableInt($raw['resource_id'] ?? null),
                    'user_id' => $userId,
                    'session_id' => $eventSession,
                    'platform' => in_array($platform, ['android', 'ios', 'web'], true) ? $platform : $platform,
                    'occurred_at' => $occurredAt,
                    'screen_name' => $screen,
                    'page_path' => $this->nullableString($raw['page_path'] ?? null, 500),
                    'app_version' => $this->nullableString($raw['app_version'] ?? null, 40),
                    'device_category' => $this->nullableString($raw['device_category'] ?? null, 40),
                    'os_name' => $this->nullableString($raw['os_name'] ?? null, 40),
                    'browser' => $this->nullableString($raw['browser'] ?? null, 80),
                    'duration_ms' => $this->nullableInt($raw['duration_ms'] ?? null),
                    'metadata' => $this->sanitizeMetadata($raw['metadata'] ?? null),
                ]);

                if ($eventSession) {
                    $this->upsertSession([
                        'session_id' => $eventSession,
                        'user_id' => $userId,
                        'platform' => $platform,
                        'device_category' => $this->nullableString($raw['device_category'] ?? null, 40),
                        'app_version' => $this->nullableString($raw['app_version'] ?? null, 40),
                        'occurred_at' => $occurredAt,
                        'screen_name' => $screen,
                        'event_type' => $eventType,
                    ]);
                }

                $recorded++;
            }
        });

        return [
            'recorded' => $recorded,
            'session_id' => $resolvedSession,
        ];
    }

    /**
     * @param  array{
     *   session_id: string,
     *   user_id: ?int,
     *   platform: ?string,
     *   device_category: ?string,
     *   app_version: ?string,
     *   occurred_at: Carbon,
     *   screen_name: ?string,
     *   event_type: string
     * }  $data
     */
    public function upsertSession(array $data): AnalyticsSession
    {
        $session = AnalyticsSession::query()->firstOrNew([
            'session_id' => $data['session_id'],
        ]);

        $occurredAt = $data['occurred_at'];
        $isNew = ! $session->exists;

        if ($isNew) {
            $session->started_at = $occurredAt;
            $session->entry_screen = $data['screen_name'];
        }

        if ($data['user_id'] && ! $session->user_id) {
            $session->user_id = $data['user_id'];
        }
        if ($data['platform']) {
            $session->platform = $data['platform'];
        }
        if ($data['device_category']) {
            $session->device_category = $data['device_category'];
        }
        if ($data['app_version']) {
            $session->app_version = $data['app_version'];
        }

        $session->last_activity_at = $occurredAt;
        if ($data['screen_name']) {
            $session->exit_screen = $data['screen_name'];
        }

        if (in_array($data['event_type'], [AnalyticsEventTypes::APP_OPEN, AnalyticsEventTypes::SESSION_START], true)
            && $isNew === false
            && $session->ended_at) {
            // Resume after explicit end: reopen.
            $session->ended_at = null;
            $session->duration_seconds = null;
        }

        if ($session->started_at) {
            $session->duration_seconds = max(
                0,
                $session->started_at->diffInSeconds($session->last_activity_at)
            );
        }

        $session->save();

        return $session;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $blocked = ['password', 'token', 'authorization', 'secret', 'api_key', 'access_token'];
        $clean = [];

        foreach ($metadata as $key => $value) {
            $keyStr = strtolower((string) $key);
            foreach ($blocked as $needle) {
                if (str_contains($keyStr, $needle)) {
                    continue 2;
                }
            }
            if (is_scalar($value) || $value === null) {
                $clean[(string) $key] = $value;
            } elseif (is_array($value)) {
                $clean[(string) $key] = Arr::map($value, fn ($v) => is_scalar($v) || $v === null ? $v : null);
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function resolveOccurredAt(mixed $value): Carbon
    {
        if (is_string($value) && $value !== '') {
            try {
                $parsed = Carbon::parse($value);
                // Reject far-future / ancient timestamps.
                if ($parsed->between(now()->subYears(2), now()->addMinutes(5))) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return now();
    }

    private function normalizeSessionId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 120);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $max);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
