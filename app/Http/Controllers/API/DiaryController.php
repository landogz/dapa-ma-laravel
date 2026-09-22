<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diary\StoreDiaryEntryRequest;
use App\Http\Requests\Diary\UpdateDiaryEntryRequest;
use App\Services\DiaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiaryController extends Controller
{
    public function __construct(
        private readonly DiaryService $diaryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $paginator = $this->diaryService->list($user);

        return response()->json([
            'status'  => true,
            'message' => 'Diary entries fetched successfully.',
            'data'    => $paginator->through(
                fn ($entry) => $this->diaryService->formatEntry($entry),
            ),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $entry = $this->diaryService->getToday($request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Today diary entry fetched successfully.',
            'data'    => $entry ? $this->diaryService->formatEntry($entry) : null,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $entry = $this->diaryService->show($request->user(), $id);

        return response()->json([
            'status'  => true,
            'message' => 'Diary entry fetched successfully.',
            'data'    => $this->diaryService->formatEntry($entry),
        ]);
    }

    public function store(StoreDiaryEntryRequest $request): JsonResponse
    {
        $entry = $this->diaryService->store(
            $request->user(),
            $this->payloadFromRequest($request),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Diary entry saved successfully.',
            'data'    => $this->diaryService->formatEntry($entry),
        ], 201);
    }

    public function update(UpdateDiaryEntryRequest $request, int $id): JsonResponse
    {
        $entry = $this->diaryService->update(
            $request->user(),
            $id,
            $this->payloadFromRequest($request),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Diary entry updated successfully.',
            'data'    => $this->diaryService->formatEntry($entry),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->diaryService->delete($request->user(), $id);

        return response()->json([
            'status'  => true,
            'message' => 'Diary entry deleted successfully.',
            'data'    => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(StoreDiaryEntryRequest|UpdateDiaryEntryRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        if ($request->boolean('remove_image')) {
            $data['remove_image'] = true;
        }

        // Multipart forms send JSON-ish list fields as repeated keys or a JSON string.
        if (isset($data['feelings']) && is_string($data['feelings'])) {
            $decoded = json_decode($data['feelings'], true);
            if (is_array($decoded)) {
                $data['feelings'] = $decoded;
            }
        }

        return $data;
    }
}
