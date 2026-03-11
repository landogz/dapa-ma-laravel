<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);

        $users = $this->userService->listPaginated($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Users fetched successfully.',
            'data' => $users,
        ]);
    }
}

