<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContestEntryRequest;
use App\Models\Contest;
use App\Services\ContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContestController extends Controller
{
    public function __construct(
        private readonly ContestService $contestService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $search = $request->get('search');
        $status = $request->get('status');
        $category = $request->get('category');

        $contests = $this->contestService->listContests(
            $perPage,
            $search,
            $status,
            $category,
            true,
            true,
        );

        return response()->json([
            'status' => true,
            'message' => 'Contests fetched successfully.',
            'data' => $contests,
        ]);
    }

    public function show(Request $request, Contest $contest): JsonResponse
    {
        if (! $contest->is_active || ! in_array($contest->status, ['open', 'closed', 'completed'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Contest not found.',
            ], 404);
        }

        $payload = $this->contestService->contestDetailForPublic($contest);
        $myEntry = null;

        $user = $request->user('sanctum');
        if ($user) {
            $myEntry = $this->contestService->myEntry($contest, $user);
        }

        return response()->json([
            'status' => true,
            'message' => 'Contest fetched successfully.',
            'data' => [
                'contest' => $payload['contest'],
                'entries' => $payload['entries'],
                'my_entry' => $myEntry,
            ],
        ]);
    }

    public function submit(StoreContestEntryRequest $request, Contest $contest): JsonResponse
    {
        try {
            $entry = $this->contestService->submitEntry(
                $contest,
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
            'message' => 'Entry submitted for admin review.',
            'data' => $entry->load('contest'),
        ], 201);
    }

    public function myEntry(Request $request, Contest $contest): JsonResponse
    {
        $entry = $this->contestService->myEntry($contest, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Your contest entry fetched successfully.',
            'data' => $entry,
        ]);
    }
}
