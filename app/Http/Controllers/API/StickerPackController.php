<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StickerPack;
use App\Services\StickerLibraryService;
use Illuminate\Http\JsonResponse;

class StickerPackController extends Controller
{
    public function __construct(
        private readonly StickerLibraryService $stickerLibraryService,
    ) {
    }

    public function index(): JsonResponse
    {
        $packs = $this->stickerLibraryService->publicPacks();

        return response()->json([
            'status' => true,
            'message' => 'Sticker packs fetched.',
            'data' => $packs->map(fn (StickerPack $pack) => $pack->toPublicArray())->values(),
        ]);
    }
}
