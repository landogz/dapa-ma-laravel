<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVideoContestEntryRequest;
use App\Http\Requests\Admin\StoreVideoContestRequest;
use App\Http\Requests\Admin\UpdateVideoContestRequest;
use App\Models\VideoContest;
use App\Models\VideoContestEntry;
use App\Services\VideoContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VideoContestAdminController extends Controller
{
    public function __construct(
        private readonly VideoContestService $videoContestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => $this->videoContestService->listContests(
                $perPage,
                $search,
                $status,
                false,
                false,
            ),
        ]);
    }

    public function show(VideoContest $videoContest): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->videoContestService->findContest($videoContest->id),
        ]);
    }

    public function store(StoreVideoContestRequest $request): JsonResponse
    {
        $contest = $this->videoContestService->createContest($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Video contest created.',
            'data' => $contest,
        ], 201);
    }

    public function update(UpdateVideoContestRequest $request, VideoContest $videoContest): JsonResponse
    {
        $contest = $this->videoContestService->updateContest($videoContest, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Video contest updated.',
            'data' => $contest,
        ]);
    }

    public function destroy(VideoContest $videoContest): JsonResponse
    {
        $this->videoContestService->deleteContest($videoContest);

        return response()->json([
            'status' => true,
            'message' => 'Video contest deleted.',
        ]);
    }

    public function entries(Request $request, VideoContest $videoContest): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 100);
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => [
                'contest' => $this->videoContestService->findContest($videoContest->id),
                'entries' => $this->videoContestService->listEntries(
                    $videoContest,
                    $perPage,
                    $status,
                    $search,
                ),
            ],
        ]);
    }

    public function review(
        ReviewVideoContestEntryRequest $request,
        VideoContestEntry $videoContestEntry,
    ): JsonResponse {
        try {
            $entry = $this->videoContestService->reviewEntry(
                $videoContestEntry,
                $request->user(),
                $request->validated('status'),
                $request->validated('admin_notes'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Review failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Entry review saved.',
            'data' => $entry,
        ]);
    }

    public function setWinner(Request $request, VideoContestEntry $videoContestEntry): JsonResponse
    {
        $entry = $this->videoContestService->setWinner($videoContestEntry, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Winner selected for this contest.',
            'data' => $entry,
        ]);
    }
}
