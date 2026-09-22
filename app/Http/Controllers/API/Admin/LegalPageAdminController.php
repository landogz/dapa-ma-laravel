<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLegalPageRequest;
use App\Services\LegalPageService;
use Illuminate\Http\JsonResponse;

class LegalPageAdminController extends Controller
{
    public function __construct(
        private readonly LegalPageService $legalPageService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Legal pages fetched.',
            'data' => $this->legalPageService->listForAdmin(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Legal page fetched.',
            'data' => $this->legalPageService->findForAdmin($slug),
        ]);
    }

    public function update(UpdateLegalPageRequest $request, string $slug): JsonResponse
    {
        $page = $this->legalPageService->update($slug, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Legal page updated.',
            'data' => $page,
        ]);
    }
}
