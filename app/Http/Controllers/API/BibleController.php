<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BibleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibleController extends Controller
{
    public function __construct(
        private readonly BibleService $bibleService,
    ) {
    }

    public function books(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString() ?: 'en';

        return response()->json([
            'status'  => true,
            'message' => 'Bible books fetched successfully.',
            'data'    => $this->bibleService->books($locale),
        ]);
    }

    public function passage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book'         => ['required', 'string', 'max:50'],
            'chapter'      => ['required', 'integer', 'min:1', 'max:150'],
            'verse_start'  => ['nullable', 'integer', 'min:1', 'max:176'],
            'verse_end'    => ['nullable', 'integer', 'min:1', 'max:176'],
            'locale'       => ['nullable', 'string', 'max:10'],
        ]);

        $passage = $this->bibleService->passage(
            $validated['book'],
            (int) $validated['chapter'],
            isset($validated['verse_start']) ? (int) $validated['verse_start'] : null,
            isset($validated['verse_end']) ? (int) $validated['verse_end'] : null,
            $validated['locale'] ?? 'en',
        );

        return response()->json([
            'status'  => true,
            'message' => 'Bible passage fetched successfully.',
            'data'    => $passage,
        ]);
    }
}
