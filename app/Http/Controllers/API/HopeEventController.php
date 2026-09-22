<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HopeEvent;
use App\Services\HopeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopeEventController extends Controller
{
    public function __construct(
        private readonly HopeEventService $hopeEventService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->hopeEventService->list(
            min(200, max(1, (int) $request->integer('per_page', 50))),
            $request->string('search')->toString() ?: null,
            $request->string('audience')->toString() ?: null,
            true,
        );

        return response()->json([
            'status'  => true,
            'message' => 'Hope events fetched successfully.',
            'data'    => $paginator->through(
                fn (HopeEvent $event) => $event->toPublicArray(false),
            ),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $event = $this->hopeEventService->show($id);

        if (!$event->is_active) {
            abort(404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Hope event fetched successfully.',
            'data'    => $event->toPublicArray(true),
        ]);
    }
}
