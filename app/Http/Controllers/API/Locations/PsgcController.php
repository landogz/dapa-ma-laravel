<?php

namespace App\Http\Controllers\API\Locations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\PsgcCitiesRequest;
use App\Http\Requests\Locations\PsgcProvincesRequest;
use App\Services\Locations\PsgcService;
use Illuminate\Http\JsonResponse;
use Throwable;

class PsgcController extends Controller
{
    public function __construct(
        private readonly PsgcService $psgcService,
    ) {
    }

    public function regions(): JsonResponse
    {
        try {
            $items = $this->psgcService->regions();
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }

        return $this->ok('Regions fetched.', $items);
    }

    public function provinces(PsgcProvincesRequest $request): JsonResponse
    {
        try {
            $items = $this->psgcService->provinces((int) $request->validated('reg'));
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }

        return $this->ok('Provinces fetched.', $items);
    }

    public function cities(PsgcCitiesRequest $request): JsonResponse
    {
        try {
            $items = $this->psgcService->cities(
                (int) $request->validated('prv'),
                $request->validated('province_name'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }

        return $this->ok('Cities fetched.', $items);
    }

    public function tree(): JsonResponse
    {
        try {
            $tree = $this->psgcService->tree();
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }

        return response()->json([
            'status' => true,
            'message' => 'Location tree fetched.',
            'data' => [
                'tree' => $tree,
            ],
        ]);
    }

    /**
     * @param  list<array{code:string,name:string,reg:int|null,prv:int|null}>  $items
     */
    private function ok(string $message, array $items): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Philippine location directory is temporarily unavailable.',
            'errors' => [],
        ], 503);
    }
}
