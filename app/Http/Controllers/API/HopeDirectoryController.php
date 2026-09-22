<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HopeDirectoryOrganization;
use App\Services\HopeDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopeDirectoryController extends Controller
{
    public function __construct(
        private readonly HopeDirectoryService $hopeDirectoryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->hopeDirectoryService->list(
            min(200, max(1, (int) $request->integer('per_page', 100))),
            $request->string('search')->toString() ?: null,
            $request->string('category')->toString() ?: null,
            true,
        );

        return response()->json([
            'status'  => true,
            'message' => 'Hope directory fetched successfully.',
            'data'    => $paginator->through(
                fn (HopeDirectoryOrganization $org) => $org->toPublicArray(),
            ),
        ]);
    }
}
