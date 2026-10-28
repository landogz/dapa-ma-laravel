<?php

namespace App\Http\Controllers\API\Admin\AdminSearch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSearch\AdminSearchRequest;
use App\Services\AdminSearch\AdminSearchService;
use Illuminate\Http\JsonResponse;

class AdminSearchController extends Controller
{
    public function __construct(
        private readonly AdminSearchService $adminSearchService,
    ) {
    }

    public function index(AdminSearchRequest $request): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => 'Search results fetched.',
            'data'    => $this->adminSearchService->search(
                $request->validated('q'),
                $request->user()?->role,
            ),
        ]);
    }
}
