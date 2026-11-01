<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ColorFunWorksheet;
use App\Services\ColorFunLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColorFunWorksheetController extends Controller
{
    public function __construct(
        private readonly ColorFunLibraryService $colorFunLibraryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $category = $request->string('category')->toString() ?: null;
        $worksheets = $this->colorFunLibraryService->publicList($category);

        return response()->json([
            'status' => true,
            'message' => 'Color Fun worksheets fetched.',
            'data' => $worksheets->map(fn (ColorFunWorksheet $worksheet) => $worksheet->toPublicArray())->values(),
        ]);
    }
}
