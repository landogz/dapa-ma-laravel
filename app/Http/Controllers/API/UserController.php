<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ProfileService $profileService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $result = $this->userService->listPaginated($perPage);
        $paginator = $result['paginator'];
        $paginator->getCollection()->transform(
            fn (User $user): array => $this->profileService->formatUser($user)
        );

        return response()->json([
            'status' => true,
            'message' => 'Users fetched successfully.',
            'data' => $paginator,
        ]);
    }
}

