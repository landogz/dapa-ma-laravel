<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSongContestEntryRequest;
use App\Http\Requests\Admin\StoreSongContestRequest;
use App\Http\Requests\Admin\UpdateSongContestRequest;
use App\Models\SongContest;
use App\Models\SongContestEntry;
use App\Services\SongContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SongContestAdminController extends Controller
{
    public function __construct(
        private readonly SongContestService $songContestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => $this->songContestService->listContests(
                $perPage,
                $search,
                $status,
                false,
                false,
            ),
        ]);
    }

    public function show(SongContest $songContest): JsonResponse
    {
        $contest = $this->songContestService->findContest($songContest->id);

        return response()->json([
            'status' => true,
            'data' => $contest,
        ]);
    }

    public function store(StoreSongContestRequest $request): JsonResponse
    {
        $contest = $this->songContestService->createContest($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Song contest created.',
            'data' => $contest,
        ], 201);
    }

    public function update(UpdateSongContestRequest $request, SongContest $songContest): JsonResponse
    {
        $contest = $this->songContestService->updateContest($songContest, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Song contest updated.',
            'data' => $contest,
        ]);
    }

    public function destroy(SongContest $songContest): JsonResponse
    {
        $this->songContestService->deleteContest($songContest);

        return response()->json([
            'status' => true,
            'message' => 'Song contest deleted.',
        ]);
    }

    public function entries(Request $request, SongContest $songContest): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 100);
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => [
                'contest' => $this->songContestService->findContest($songContest->id),
                'entries' => $this->songContestService->listEntries(
                    $songContest,
                    $perPage,
                    $status,
                    $search,
                ),
            ],
        ]);
    }

    public function review(
        ReviewSongContestEntryRequest $request,
        SongContestEntry $songContestEntry,
    ): JsonResponse {
        try {
            $entry = $this->songContestService->reviewEntry(
                $songContestEntry,
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

    public function setWinner(Request $request, SongContestEntry $songContestEntry): JsonResponse
    {
        $entry = $this->songContestService->setWinner($songContestEntry, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Winner selected for this contest.',
            'data' => $entry,
        ]);
    }
}
