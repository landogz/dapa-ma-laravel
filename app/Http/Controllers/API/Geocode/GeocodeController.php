<?php

namespace App\Http\Controllers\API\Geocode;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geocode\GeocodeReverseRequest;
use App\Http\Requests\Geocode\GeocodeSearchRequest;
use App\Services\Geocode\GeocodeService;
use Illuminate\Http\JsonResponse;
use Throwable;

class GeocodeController extends Controller
{
    public function __construct(
        private readonly GeocodeService $geocodeService,
    ) {
    }

    public function search(GeocodeSearchRequest $request): JsonResponse
    {
        try {
            $results = $this->geocodeService->search($request->validated('q'));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Address search is temporarily unavailable.',
                'errors' => [],
            ], 503);
        }

        return response()->json([
            'status' => true,
            'message' => 'Address suggestions fetched.',
            'data' => [
                'results' => $results,
            ],
        ]);
    }

    public function reverse(GeocodeReverseRequest $request): JsonResponse
    {
        try {
            $place = $this->geocodeService->reverse(
                (float) $request->validated('lat'),
                (float) $request->validated('lng'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Reverse geocoding is temporarily unavailable.',
                'errors' => [],
            ], 503);
        }

        return response()->json([
            'status' => true,
            'message' => 'Address resolved.',
            'data' => $place,
        ]);
    }
}
