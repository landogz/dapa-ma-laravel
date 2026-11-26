<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreRelaxingMusicTrackRequest;
use App\Http\Requests\Admin\UpdateRelaxingMusicTrackRequest;
use App\Models\RelaxingMusicTrack;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Services\RelaxingMusicService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelaxingMusicTrackAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        archive as protected workflowArchive;
        restore as protected workflowRestore;
        destroy as protected workflowDestroy;
    }

    public function __construct(
        private readonly RelaxingMusicService $relaxingMusicService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'relaxing-music';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'is_active' => $request->has('is_active')
                ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'workflow_status' => $request->string('workflow_status')->toString() ?: null,
        ];

        $tracks = $this->relaxingMusicService->listAdmin($filters, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Relaxing music tracks fetched.',
            'data' => $tracks->map(fn (RelaxingMusicTrack $track) => $track->toAdminArray())->values(),
            'meta' => [
                'status_counts' => $this->relaxingMusicService->statusCounts($request->user()),
            ],
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

        $actor = $request->user();
        $tracks = $this->relaxingMusicService->createMany($data, $actor);
        $count = count($tracks);
        $submitted = $actor?->role === 'editor';

        return response()->json([
            'status' => true,
            'message' => $count === 1
                ? ($submitted ? 'Track submitted for publisher review.' : 'Track created.')
                : ($submitted
                    ? "{$count} tracks submitted for publisher review."
                    : "{$count} tracks created."),
            'data' => $count === 1
                ? $tracks[0]->toAdminArray()
                : array_map(static fn ($track) => $track->toAdminArray(), $tracks),
        ], 201);
    }

    public function show(Request $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        return response()->json([
            'status' => true,
            'message' => 'Relaxing music track fetched.',
            'data' => $relaxingMusicTrack->toAdminArray(),
        ]);
    }

    public function update(UpdateRelaxingMusicTrackRequest $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        $data = $request->validated();
        if ($request->hasFile('audio')) {
            $data['audio'] = $request->file('audio');
        }

        try {
            $updated = $this->relaxingMusicService->update($relaxingMusicTrack, $data, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Track updated.',
            'data' => $updated->toAdminArray(),
        ]);
    }

    public function submit(Request $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        return $this->workflowSubmit($request, $relaxingMusicTrack);
    }

    public function reject(RejectContentRequest $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        return $this->workflowReject($request, $relaxingMusicTrack);
    }

    public function publish(Request $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        return $this->workflowPublish($request, $relaxingMusicTrack);
    }

    public function archive(Request $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        return $this->workflowArchive($request, $relaxingMusicTrack);
    }

    public function restore(Request $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $relaxingMusicTrack);

        return $this->workflowRestore($request, $relaxingMusicTrack);
    }

    public function destroy(Request $request, RelaxingMusicTrack $relaxingMusicTrack): JsonResponse
    {
        return $this->workflowDestroy($request, $relaxingMusicTrack);
    }

    protected function permanentlyDeleteDraft(Model $model): void
    {
        $this->relaxingMusicService->delete($model);
    }
}
