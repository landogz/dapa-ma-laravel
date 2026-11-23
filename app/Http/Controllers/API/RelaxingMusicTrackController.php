<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RelaxingMusicTrack;
use App\Services\RelaxingMusicService;
use Illuminate\Http\JsonResponse;

class RelaxingMusicTrackController extends Controller
{
    public function __construct(
        private readonly RelaxingMusicService $relaxingMusicService,
    ) {
    }

    public function index(): JsonResponse
    {
        $tracks = $this->relaxingMusicService->publicList();

        return response()->json([
            'status' => true,
            'message' => 'Relaxing music tracks fetched.',
            'data' => $tracks->map(fn (RelaxingMusicTrack $track) => $track->toPublicArray())->values(),
        ]);
    }
}
