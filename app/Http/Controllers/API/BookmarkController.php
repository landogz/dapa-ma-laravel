<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BookmarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function __construct(
        private readonly BookmarkService $bookmarkService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $bookmarks = $this->bookmarkService->list($user);

        return response()->json([
            'status'  => true,
            'message' => 'Bookmarks fetched successfully.',
            'data'    => $bookmarks,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user   = $request->user();
        $postId = (int) $request->integer('post_id');

        if (! $postId) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'post_id' => ['The post_id field is required.'],
                ],
            ], 422);
        }

        $this->bookmarkService->toggle($user, $postId);

        return response()->json([
            'status'  => true,
            'message' => 'Bookmark updated successfully.',
            'data'    => null,
        ]);
    }
}

