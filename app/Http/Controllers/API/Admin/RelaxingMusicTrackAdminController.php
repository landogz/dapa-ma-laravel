<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRelaxingMusicTrackRequest;
use App\Http\Requests\Admin\UpdateRelaxingMusicTrackRequest;
use App\Models\RelaxingMusicTrack;
use App\Services\RelaxingMusicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelaxingMusicTrackAdminController extends Controller
{
    public function __construct(
        private readonly RelaxingMusicService $relaxingMusicService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tracks = $this->relaxingMusicService->list(
            $request->string('search')->toString() ?: null,
        );

        return response()->json([
            'status' => true,
            'message' => 'Relaxing music tracks fetched.',
            'data' => $tracks->map(fn (RelaxingMusicTrack $track) => $track->toAdminArray())->values(),
        ]);
    }

    public function store(StoreRelaxingMusicTrackRequest $request): JsonResponse
    {
        $data = $request->validated();
        $audios = $request->file('audios');
        if (is_array($audios) && $audios !== []) {
            $data['audios'] = $audios;
        } elseif ($request->hasFile('audio')) {
            $data['audio'] = $request->file('audio');
        }

        $tracks = $this->relaxingMusicService->createMany($data);
        $count = count($tracks);

        return response()->json([
            'status' => true,
            'message' => $count === 1
                ? 'Track created.'
                : "{$count} tracks created.",
            'data' => $count === 1
                ? $tracks[0]->toAdminArray()
                : array_map(static fn ($track) => $track->toAdminArray(), $tracks),
        ], 201);
    }

    public function show(RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Relaxing music track fetched.',
            'data' => $relaxingMusicTrack->toAdminArray(),
        ]);
    }

    public function update(UpdateRelaxingMusicTrackRequest $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('audio')) {
            $data['audio'] = $request->file('audio');
        }

        $updated = $this->relaxingMusicService->update($relaxingMusicTrack, $data);

        return response()->json([
            'status' => true,
            'message' => 'Track updated.',
            'data' => $updated->toAdminArray(),
        ]);
    }

    public function destroy(RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->relaxingMusicService->delete($relaxingMusicTrack);

        return response()->json([
            'status' => true,
            'message' => 'Track deleted.',
        ]);
    }
}
