<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'post_id'    => ['nullable', 'integer', 'exists:posts,id'],
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => $data['event_type'],
            'post_id'    => $data['post_id'] ?? null,
            'user_id'    => optional($request->user())->id,
            'session_id' => $request->header('X-Session-Id') ?: $request->session()->getId(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Event recorded.',
            'data'    => null,
        ], 201);
    }
}

