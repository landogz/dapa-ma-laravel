<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSongContestEntryRequest;
use App\Models\SongContest;
use App\Services\SongContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SongContestController extends Controller
{
    public function __construct(
        private readonly SongContestService $songContestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->get('search');
        $status = $request->get('status');

        $contests = $this->songContestService->listContests(
            $perPage,
            $search,
            $status,
            true,
            true,
        );

        return response()->json([
            'status' => true,
            'message' => 'Song contests fetched successfully.',
            'data' => $contests,
        ]);
    }

    public function show(Request $request, SongContest $songContest): JsonResponse
    {
        if (! $songContest->is_active || ! in_array($songContest->status, ['open', 'closed', 'completed'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Song contest not found.',
            ], 404);
        }

        $payload = $this->songContestService->contestDetailForPublic($songContest);
        $myEntry = null;

        $user = $request->user('sanctum');
        if ($user) {
            $myEntry = $this->songContestService->myEntry($songContest, $user);
        }

        return response()->json([
            'status' => true,
            'message' => 'Song contest fetched successfully.',
            'data' => [
                'contest' => $payload['contest'],
                'entries' => $payload['entries'],
                'my_entry' => $myEntry,
            ],
        ]);
    }

    public function submit(StoreSongContestEntryRequest $request, SongContest $songContest): JsonResponse
    {
        try {
            $entry = $this->songContestService->submitEntry(
                $songContest,
                $request->user(),
                $request->validated(),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Unable to submit entry.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Entry submitted for admin review.',
            'data' => $entry->load('contest'),
        ], 201);
    }

    public function myEntry(Request $request, SongContest $songContest): JsonResponse
    {
        $entry = $this->songContestService->myEntry($songContest, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Your contest entry fetched successfully.',
            'data' => $entry,
        ]);
    }
}
