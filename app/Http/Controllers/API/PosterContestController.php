<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePosterContestEntryRequest;
use App\Models\PosterContest;
use App\Services\PosterContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosterContestController extends Controller
{
    public function __construct(
        private readonly PosterContestService $posterContestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->get('search');
        $status = $request->get('status');

        $contests = $this->posterContestService->listContests(
            $perPage,
            $search,
            $status,
            true,
            true,
        );

        return response()->json([
            'status' => true,
            'message' => 'Poster contests fetched successfully.',
            'data' => $contests,
        ]);
    }

    public function show(Request $request, PosterContest $posterContest): JsonResponse
    {
        if (! $posterContest->is_active || ! in_array($posterContest->status, ['open', 'closed', 'completed'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Poster contest not found.',
            ], 404);
        }

        $payload = $this->posterContestService->contestDetailForPublic($posterContest);
        $myEntry = null;

        $user = $request->user('sanctum');
        if ($user) {
            $myEntry = $this->posterContestService->myEntry($posterContest, $user);
        }

        return response()->json([
            'status' => true,
            'message' => 'Poster contest fetched successfully.',
            'data' => [
                'contest' => $payload['contest'],
                'entries' => $payload['entries'],
                'my_entry' => $myEntry,
            ],
        ]);
    }

    public function submit(StorePosterContestEntryRequest $request, PosterContest $posterContest): JsonResponse
    {
        try {
            $entry = $this->posterContestService->submitEntry(
                $posterContest,
                $request->user(),
                $request->validated(),
                $request->file('poster_image'),
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
            'message' => 'Poster entry submitted for admin review.',
            'data' => $entry->load('contest'),
        ], 201);
    }

    public function myEntry(Request $request, PosterContest $posterContest): JsonResponse
    {
        $entry = $this->posterContestService->myEntry($posterContest, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Your poster contest entry fetched successfully.',
            'data' => $entry,
        ]);
    }
}
