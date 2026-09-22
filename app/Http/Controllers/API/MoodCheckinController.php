<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mood\StoreMoodCheckinRequest;
use App\Services\MoodCheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodCheckinController extends Controller
{
    public function __construct(
        private readonly MoodCheckinService $moodCheckinService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->moodCheckinService->list($request->user(), $perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Mood check-ins fetched successfully.',
            'data'    => $paginator->through(
                fn ($item) => $this->moodCheckinService->format($item),
            ),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $checkin = $this->moodCheckinService->today($request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Today mood check-in fetched successfully.',
            'data'    => $checkin ? $this->moodCheckinService->format($checkin) : null,
        ]);
    }

    public function store(StoreMoodCheckinRequest $request): JsonResponse
    {
        $checkin = $this->moodCheckinService->store(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Mood check-in saved.',
            'data'    => $this->moodCheckinService->format($checkin),
        ], 201);
    }
}
