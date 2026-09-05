<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoContestEntryRequest;
use App\Models\VideoContest;
use App\Services\VideoContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VideoContestController extends Controller
{
    public function __construct(
        private readonly VideoContestService $videoContestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->get('search');
        $status = $request->get('status');

        $contests = $this->videoContestService->listContests(
            $perPage,
            $search,
            $status,
            true,
            true,
        );

        return response()->json([
            'status' => true,
            'message' => 'Video contests fetched successfully.',
            'data' => $contests,
        ]);
    }

    public function show(Request $request, VideoContest $videoContest): JsonResponse
    {
        if (! $videoContest->is_active || ! in_array($videoContest->status, ['open', 'closed', 'completed'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Video contest not found.',
            ], 404);
        }

        $payload = $this->videoContestService->contestDetailForPublic($videoContest);
        $myEntry = null;

        $user = $request->user('sanctum');
        if ($user) {
            $myEntry = $this->videoContestService->myEntry($videoContest, $user);
        }

        return response()->json([
            'status' => true,
            'message' => 'Video contest fetched successfully.',
            'data' => [
                'contest' => $payload['contest'],
                'entries' => $payload['entries'],
                'my_entry' => $myEntry,
            ],
        ]);
    }

    public function submit(StoreVideoContestEntryRequest $request, VideoContest $videoContest): JsonResponse
    {
        try {
            $entry = $this->videoContestService->submitEntry(
                $videoContest,
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
            'message' => 'Video entry submitted for admin review.',
            'data' => $entry->load('contest'),
        ], 201);
    }

    public function myEntry(Request $request, VideoContest $videoContest): JsonResponse
    {
        $entry = $this->videoContestService->myEntry($videoContest, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Your video contest entry fetched successfully.',
            'data' => $entry,
        ]);
    }
}
