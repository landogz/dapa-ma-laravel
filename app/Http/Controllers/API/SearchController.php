<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = (string) $request->get('q', '');
        $category = (string) $request->get('category', '');

        if ($query === '') {
            return response()->json([
                'status'  => true,
                'message' => 'Empty query, returning no results.',
                'data'    => [
                    'posts'         => [],
                    'rehab_centers' => [],
                    'suggestions'   => [],
                ],
            ]);
        }

        $results     = $this->searchService->search($query, $category !== '' ? $category : null);
        $suggestions = $this->searchService->suggest($query);

        return response()->json([
            'status'  => true,
            'message' => 'Search results fetched successfully.',
            'data'    => [
                'posts'         => $results['posts'],
                'rehab_centers' => $results['rehab_centers'],
                'suggestions'   => $suggestions,
            ],
        ]);
    }
}

