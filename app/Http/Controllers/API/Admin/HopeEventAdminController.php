<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HopeEvent\StoreHopeEventRequest;
use App\Http\Requests\HopeEvent\UpdateHopeEventRequest;
use App\Models\HopeEvent;
use App\Services\HopeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopeEventAdminController extends Controller
{
    public function __construct(
        private readonly HopeEventService $hopeEventService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->hopeEventService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('audience')->toString() ?: null,
            false,
        );

        return response()->json([
            'status'  => true,
            'message' => 'Hope events fetched.',
            'data'    => $paginator->through(
                fn (HopeEvent $event) => $event->toAdminArray(),
            ),
        ]);
    }

    public function show(HopeEvent $hopeEvent): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data'   => $hopeEvent->toAdminArray(),
        ]);
    }

    public function store(StoreHopeEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover');
        }

        $event = $this->hopeEventService->create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Hope event created.',
            'data'    => $event->toAdminArray(),
        ], 201);
    }

    public function update(UpdateHopeEventRequest $request, HopeEvent $hopeEvent): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover');
        }
        if ($request->boolean('remove_cover')) {
            $data['remove_cover'] = true;
        }

        $event = $this->hopeEventService->update($hopeEvent, $data);

        return response()->json([
            'status'  => true,
            'message' => 'Hope event updated.',
            'data'    => $event->toAdminArray(),
        ]);
    }

    public function destroy(HopeEvent $hopeEvent): JsonResponse
    {
        $this->hopeEventService->delete($hopeEvent);

        return response()->json([
            'status'  => true,
            'message' => 'Hope event deleted.',
        ]);
    }
}
