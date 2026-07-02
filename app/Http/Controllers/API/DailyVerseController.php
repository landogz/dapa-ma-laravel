<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DailyVerseService;
use Illuminate\Http\JsonResponse;

class DailyVerseController extends Controller
{
    public function __construct(
        private readonly DailyVerseService $dailyVerseService,
    ) {
    }

    public function today(): JsonResponse
    {
        $verse = $this->dailyVerseService->forToday();

        return response()->json([
            'status'  => true,
            'message' => 'Daily verse fetched successfully.',
            'data'    => $verse,
        ]);
    }
}
