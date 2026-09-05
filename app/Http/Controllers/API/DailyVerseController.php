<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DailyVerseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyVerseController extends Controller
{
    public function __construct(
        private readonly DailyVerseService $dailyVerseService,
    ) {
    }

    public function today(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString() ?: null;
        $verse = $this->dailyVerseService->forToday($locale);

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo Says message fetched successfully.',
            'data' => $verse,
        ]);
    }
}
