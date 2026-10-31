<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Analytics\AnalyticsIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AnalyticsEventController extends Controller
{
    public function __construct(
        private readonly AnalyticsIngestService $ingestService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $isBatch = $request->has('events') && is_array($request->input('events'));

        if ($isBatch) {
            $validated = $request->validate([
                'events' => ['required', 'array', 'min:1', 'max:'.AnalyticsIngestService::MAX_BATCH],
                'events.*.event_type' => ['required', 'string', 'max:100'],
                'events.*.post_id' => ['nullable', 'integer', 'exists:posts,id'],
                'events.*.platform' => ['nullable', 'string', 'in:android,ios,web'],
                'events.*.resource_type' => ['nullable', 'string', 'max:50'],
                'events.*.resource_id' => ['nullable', 'integer', 'min:1'],
                'events.*.session_id' => ['nullable', 'string', 'max:120'],
                'events.*.occurred_at' => ['nullable', 'date'],
                'events.*.screen_name' => ['nullable', 'string', 'max:120'],
                'events.*.page_path' => ['nullable', 'string', 'max:500'],
                'events.*.app_version' => ['nullable', 'string', 'max:40'],
                'events.*.device_category' => ['nullable', 'string', 'max:40'],
                'events.*.os_name' => ['nullable', 'string', 'max:40'],
                'events.*.browser' => ['nullable', 'string', 'max:80'],
                'events.*.duration_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
                'events.*.metadata' => ['nullable', 'array'],
            ]);

            $result = $this->ingestService->ingestMany(
                $validated['events'],
                $this->resolveUserId($request),
                $this->resolveSessionId($request),
            );
        } else {
            $validated = $request->validate([
                'event_type' => ['required', 'string', 'max:100'],
                'post_id' => ['nullable', 'integer', 'exists:posts,id'],
                'platform' => ['nullable', 'string', Rule::in(['android', 'ios', 'web'])],
                'resource_type' => ['nullable', 'string', 'max:50'],
                'resource_id' => ['nullable', 'integer', 'min:1'],
                'session_id' => ['nullable', 'string', 'max:120'],
                'occurred_at' => ['nullable', 'date'],
                'screen_name' => ['nullable', 'string', 'max:120'],
                'page_path' => ['nullable', 'string', 'max:500'],
                'app_version' => ['nullable', 'string', 'max:40'],
                'device_category' => ['nullable', 'string', 'max:40'],
                'os_name' => ['nullable', 'string', 'max:40'],
                'browser' => ['nullable', 'string', 'max:80'],
                'duration_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
                'metadata' => ['nullable', 'array'],
            ]);

            $result = $this->ingestService->ingestOne(
                $validated,
                $this->resolveUserId($request),
                $this->resolveSessionId($request) ?? ($validated['session_id'] ?? null),
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Event recorded.',
            'data' => [
                'recorded' => $result['recorded'],
                'session_id' => $result['session_id'],
            ],
        ], 201);
    }

    private function resolveUserId(Request $request): ?int
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $user = $accessToken?->tokenable;

        return $user instanceof User ? $user->id : null;
    }

    private function resolveSessionId(Request $request): ?string
    {
        $headerSession = $request->header('X-Session-Id');

        if (is_string($headerSession) && $headerSession !== '') {
            return Str::limit($headerSession, 120, '');
        }

        if ($request->filled('session_id')) {
            return Str::limit((string) $request->input('session_id'), 120, '');
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return null;
    }
}
