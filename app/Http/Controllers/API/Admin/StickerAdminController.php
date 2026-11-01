<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderStickersRequest;
use App\Http\Requests\Admin\StoreStickerRequest;
use App\Http\Requests\Admin\UpdateStickerRequest;
use App\Models\Sticker;
use App\Services\StickerLibraryService;
use Illuminate\Http\JsonResponse;

class StickerAdminController extends Controller
{
    public function __construct(
        private readonly StickerLibraryService $stickerLibraryService,
    ) {
    }

    public function store(StoreStickerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $images = $request->file('images');
        if (is_array($images) && $images !== []) {
            $data['images'] = $images;
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $stickers = $this->stickerLibraryService->createStickers($data);
        $count = count($stickers);

        return response()->json([
            'status' => true,
            'message' => $count === 1
                ? 'Sticker created.'
                : "{$count} stickers created.",
            'data' => $count === 1
                ? $stickers[0]->toAdminArray()
                : array_map(static fn ($sticker) => $sticker->toAdminArray(), $stickers),
        ], 201);
    }

    public function update(UpdateStickerRequest $request, Sticker $sticker): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $updated = $this->stickerLibraryService->updateSticker($sticker, $data);

        return response()->json([
            'status' => true,
            'message' => 'Sticker updated.',
            'data' => $updated->toAdminArray(),
        ]);
    }

    public function destroy(Sticker $sticker): JsonResponse
    {
        $this->stickerLibraryService->deleteSticker($sticker);

        return response()->json([
            'status' => true,
            'message' => 'Sticker deleted.',
        ]);
    }

    public function reorder(ReorderStickersRequest $request): JsonResponse
    {
        $this->stickerLibraryService->reorderStickers($request->validated('items'));

        return response()->json([
            'status' => true,
            'message' => 'Stickers reordered.',
        ]);
    }
}
