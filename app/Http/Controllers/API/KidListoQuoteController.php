<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\KidListoQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KidListoQuoteController extends Controller
{
    public function __construct(
        private readonly KidListoQuoteService $kidListoQuoteService,
    ) {
    }

    public function random(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString() ?: 'en';
        $quote = $this->kidListoQuoteService->randomPublic($locale);

        if (! $quote) {
            return response()->json([
                'status' => false,
                'message' => 'No Kid Listo quotes available.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quote fetched.',
            'data' => $quote,
        ]);
    }
}
