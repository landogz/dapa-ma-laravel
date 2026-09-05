<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPosterContestEntryRequest;
use App\Http\Requests\Admin\StorePosterContestRequest;
use App\Http\Requests\Admin\UpdatePosterContestRequest;
use App\Models\PosterContest;
use App\Models\PosterContestEntry;
use App\Services\PosterContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosterContestAdminController extends Controller
{
    public function __construct(
        private readonly PosterContestService $posterContestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => $this->posterContestService->listContests(
                $perPage,
                $search,
                $status,
                false,
                false,
            ),
        ]);
    }

    public function show(PosterContest $posterContest): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->posterContestService->findContest($posterContest->id),
        ]);
    }

    public function store(StorePosterContestRequest $request): JsonResponse
    {
        $contest = $this->posterContestService->createContest($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Poster contest created.',
            'data' => $contest,
        ], 201);
    }

    public function update(UpdatePosterContestRequest $request, PosterContest $posterContest): JsonResponse
    {
        $contest = $this->posterContestService->updateContest($posterContest, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Poster contest updated.',
            'data' => $contest,
        ]);
    }

    public function destroy(PosterContest $posterContest): JsonResponse
    {
        $this->posterContestService->deleteContest($posterContest);

        return response()->json([
            'status' => true,
            'message' => 'Poster contest deleted.',
        ]);
    }

    public function entries(Request $request, PosterContest $posterContest): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 100);
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => [
                'contest' => $this->posterContestService->findContest($posterContest->id),
                'entries' => $this->posterContestService->listEntries(
                    $posterContest,
                    $perPage,
                    $status,
                    $search,
                ),
            ],
        ]);
    }

    public function review(
        ReviewPosterContestEntryRequest $request,
        PosterContestEntry $posterContestEntry,
    ): JsonResponse {
        try {
            $entry = $this->posterContestService->reviewEntry(
                $posterContestEntry,
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

    public function setWinner(Request $request, PosterContestEntry $posterContestEntry): JsonResponse
    {
        $entry = $this->posterContestService->setWinner($posterContestEntry, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Winner selected for this contest.',
            'data' => $entry,
        ]);
    }
}
