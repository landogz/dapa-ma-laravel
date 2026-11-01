<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreColorFunWorksheetRequest;
use App\Http\Requests\Admin\UpdateColorFunWorksheetRequest;
use App\Models\ColorFunWorksheet;
use App\Services\ColorFunLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColorFunWorksheetAdminController extends Controller
{
    public function __construct(
        private readonly ColorFunLibraryService $colorFunLibraryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $worksheets = $this->colorFunLibraryService->list(
            $request->string('search')->toString() ?: null,
            $request->string('category')->toString() ?: null,
        );

        return response()->json([
            'status' => true,
            'message' => 'Color Fun worksheets fetched.',
            'data' => $worksheets->map(fn (ColorFunWorksheet $worksheet) => $worksheet->toAdminArray())->values(),
        ]);
    }

    public function store(StoreColorFunWorksheetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $images = $request->file('images');
        if (is_array($images) && $images !== []) {
            $data['images'] = $images;
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $worksheets = $this->colorFunLibraryService->createMany($data);
        $count = count($worksheets);

        return response()->json([
            'status' => true,
            'message' => $count === 1
                ? 'Worksheet created.'
                : "{$count} worksheets created.",
            'data' => $count === 1
                ? $worksheets[0]->toAdminArray()
                : array_map(static fn ($worksheet) => $worksheet->toAdminArray(), $worksheets),
        ], 201);
    }

    public function show(ColorFunWorksheet $colorFunWorksheet): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Color Fun worksheet fetched.',
            'data' => $colorFunWorksheet->toAdminArray(),
        ]);
    }

    public function update(UpdateColorFunWorksheetRequest $request, ColorFunWorksheet $colorFunWorksheet): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $updated = $this->colorFunLibraryService->update($colorFunWorksheet, $data);

        return response()->json([
            'status' => true,
            'message' => 'Worksheet updated.',
            'data' => $updated->toAdminArray(),
        ]);
    }

    public function destroy(ColorFunWorksheet $colorFunWorksheet): JsonResponse
    {
        $this->colorFunLibraryService->delete($colorFunWorksheet);

        return response()->json([
            'status' => true,
            'message' => 'Worksheet deleted.',
        ]);
    }
}
