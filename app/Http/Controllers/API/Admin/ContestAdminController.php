<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewContestEntryRequest;
use App\Http\Requests\Admin\StoreContestRequest;
use App\Http\Requests\Admin\UpdateContestRequest;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Services\ContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContestAdminController extends Controller
{
    public function __construct(
        private readonly ContestService $contestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $category = $request->string('category')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => $this->contestService->listContests(
                $perPage,
                $search,
                $status,
                $category,
                false,
                false,
            ),
        ]);
    }

    public function show(Contest $contest): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->contestService->findContest($contest->id),
        ]);
    }

    public function store(StoreContestRequest $request): JsonResponse
    {
        $contest = $this->contestService->createContest($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Contest created.',
            'data' => $contest,
        ], 201);
    }

    public function update(UpdateContestRequest $request, Contest $contest): JsonResponse
    {
        $contest = $this->contestService->updateContest($contest, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Contest updated.',
            'data' => $contest,
        ]);
    }

    public function destroy(Contest $contest): JsonResponse
    {
        $this->contestService->deleteContest($contest);

        return response()->json([
            'status' => true,
            'message' => 'Contest deleted.',
        ]);
    }

    public function entries(Request $request, Contest $contest): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 100);
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => [
                'contest' => $this->contestService->findContest($contest->id),
                'entries' => $this->contestService->listEntries(
                    $contest,
                    $perPage,
                    $status,
                    $search,
                ),
            ],
        ]);
    }

    public function review(
        ReviewContestEntryRequest $request,
        ContestEntry $contestEntry,
    ): JsonResponse {
        try {
            $entry = $this->contestService->reviewEntry(
                $contestEntry,
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

    public function setWinner(Request $request, ContestEntry $contestEntry): JsonResponse
    {
        $entry = $this->contestService->setWinner($contestEntry, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Winner selected for this contest.',
            'data' => $entry,
        ]);
    }
}
