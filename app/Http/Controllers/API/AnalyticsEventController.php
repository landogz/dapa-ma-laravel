<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AnalyticsEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'post_id'    => ['nullable', 'integer', 'exists:posts,id'],
            'platform'   => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => $data['event_type'],
            'post_id'    => $data['post_id'] ?? null,
            'user_id'    => $this->resolveUserId($request),
            'session_id' => $this->resolveSessionId($request),
            'platform'   => $data['platform'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Event recorded.',
            'data'    => null,
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

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return null;
    }
}

