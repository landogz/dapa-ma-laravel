<?php

namespace App\Http\Controllers\API\Admin\IconPicker;

use App\Http\Controllers\Controller;
use App\Services\IconPicker\IconPickerService;
use Illuminate\Http\JsonResponse;

class IconPickerController extends Controller
{
    public function __construct(
        private readonly IconPickerService $iconPickerService,
    ) {
    }

    public function iconsets(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Iconsets fetched.',
            'data' => $this->iconPickerService->iconsets(),
        ]);
    }

    public function icons(string $iconset): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Icons fetched.',
            'data' => $this->iconPickerService->icons($iconset),
        ]);
    }
}
