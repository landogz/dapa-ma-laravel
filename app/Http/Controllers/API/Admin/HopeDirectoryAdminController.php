<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HopeDirectory\StoreHopeDirectoryOrganizationRequest;
use App\Http\Requests\HopeDirectory\UpdateHopeDirectoryOrganizationRequest;
use App\Models\HopeDirectoryOrganization;
use App\Services\HopeDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopeDirectoryAdminController extends Controller
{
    public function __construct(
        private readonly HopeDirectoryService $hopeDirectoryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->hopeDirectoryService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('category')->toString() ?: null,
            false,
        );

        return response()->json([
            'status'  => true,
            'message' => 'Hope directory organizations fetched.',
            'data'    => $paginator->through(
                fn (HopeDirectoryOrganization $org) => $org->toAdminArray(),
            ),
        ]);
    }

    public function show(HopeDirectoryOrganization $hopeDirectory): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data'   => $hopeDirectory->toAdminArray(),
        ]);
    }

    public function store(StoreHopeDirectoryOrganizationRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo');
        }

        $org = $this->hopeDirectoryService->create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Directory organization created.',
            'data'    => $org->toAdminArray(),
        ], 201);
    }

    public function update(
        UpdateHopeDirectoryOrganizationRequest $request,
        HopeDirectoryOrganization $hopeDirectory,
    ): JsonResponse {
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo');
        }
        if ($request->boolean('remove_logo')) {
            $data['remove_logo'] = true;
        }

        $org = $this->hopeDirectoryService->update($hopeDirectory, $data);

        return response()->json([
            'status'  => true,
            'message' => 'Directory organization updated.',
            'data'    => $org->toAdminArray(),
        ]);
    }

    public function destroy(HopeDirectoryOrganization $hopeDirectory): JsonResponse
    {
        $this->hopeDirectoryService->delete($hopeDirectory);

        return response()->json([
            'status'  => true,
            'message' => 'Directory organization deleted.',
        ]);
    }
}
