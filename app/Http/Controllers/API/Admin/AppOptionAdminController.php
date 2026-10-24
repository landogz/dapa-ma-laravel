<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppOptionRequest;
use App\Http\Requests\Admin\UpdateAppOptionRequest;
use App\Models\AppOption;
use App\Services\AppOptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppOptionAdminController extends Controller
{
    public function __construct(
        private readonly AppOptionService $appOptionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->appOptionService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('group')->toString() ?: null,
        );

        return response()->json([
            'status' => true,
            'message' => 'App options fetched.',
            'data' => $paginator->through(fn (AppOption $option) => $option->toAdminArray()),
            'meta' => $this->appOptionService->adminMeta(),
        ]);
    }

    public function show(AppOption $appOption): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $appOption->toAdminArray(),
        ]);
    }

    public function store(StoreAppOptionRequest $request): JsonResponse
    {
        $option = $this->appOptionService->create($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Option created.',
            'data' => $option->toAdminArray(),
        ], 201);
    }

    public function update(UpdateAppOptionRequest $request, AppOption $appOption): JsonResponse
    {
        $option = $this->appOptionService->update($appOption, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Option updated.',
            'data' => $option->toAdminArray(),
        ]);
    }

    public function destroy(AppOption $appOption): JsonResponse
    {
        $this->appOptionService->delete($appOption);

        return response()->json([
            'status' => true,
            'message' => 'Option deleted.',
        ]);
    }
}
