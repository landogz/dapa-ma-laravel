<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderStickersRequest;
use App\Http\Requests\Admin\StoreStickerPackRequest;
use App\Http\Requests\Admin\UpdateStickerPackRequest;
use App\Models\StickerPack;
use App\Services\StickerLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StickerPackAdminController extends Controller
{
    public function __construct(
        private readonly StickerLibraryService $stickerLibraryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $packs = $this->stickerLibraryService->listPacks(
            $request->string('search')->toString() ?: null,
        );

        return response()->json([
            'status' => true,
            'message' => 'Sticker packs fetched.',
            'data' => $packs->map(fn (StickerPack $pack) => $pack->toAdminArray())->values(),
        ]);
    }

    public function show(StickerPack $stickerPack): JsonResponse
    {
        $pack = $this->stickerLibraryService->findPack($stickerPack->id);

        return response()->json([
            'status' => true,
            'data' => $pack->toAdminArray(),
        ]);
    }

    public function store(StoreStickerPackRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover');
        }

        $pack = $this->stickerLibraryService->createPack($data);

        return response()->json([
            'status' => true,
            'message' => 'Sticker pack created.',
            'data' => $pack->toAdminArray(),
        ], 201);
    }

    public function update(UpdateStickerPackRequest $request, StickerPack $stickerPack): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover');
        }
        if ($request->boolean('remove_cover')) {
            $data['remove_cover'] = true;
        }

        $pack = $this->stickerLibraryService->updatePack($stickerPack, $data);

        return response()->json([
            'status' => true,
            'message' => 'Sticker pack updated.',
            'data' => $pack->toAdminArray(),
        ]);
    }

    public function destroy(StickerPack $stickerPack): JsonResponse
    {
        $this->stickerLibraryService->deletePack($stickerPack);

        return response()->json([
            'status' => true,
            'message' => 'Sticker pack deleted.',
        ]);
    }

    public function reorder(ReorderStickersRequest $request): JsonResponse
    {
        $this->stickerLibraryService->reorderPacks($request->validated('items'));

        return response()->json([
            'status' => true,
            'message' => 'Sticker packs reordered.',
        ]);
    }
}
