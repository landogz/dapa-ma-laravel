<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\HopeEvent\StoreHopeEventRequest;
use App\Http\Requests\HopeEvent\UpdateHopeEventRequest;
use App\Models\HopeEvent;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Services\HopeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopeEventAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
    }

    public function __construct(
        private readonly HopeEventService $hopeEventService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'hope-events';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
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

    public function options(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Hope event options loaded successfully.',
            'data' => $this->hopeEventService->listForSelection()->map(static fn (HopeEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'is_active' => (bool) $event->is_active,
            ])->values(),
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
        $data['speaker_photos'] = $this->collectSpeakerPhotos($request);

        $event = $this->hopeEventService->create($data, $request->user());

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
        $data['speaker_photos'] = $this->collectSpeakerPhotos($request);

        $event = $this->hopeEventService->update($hopeEvent, $data, $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Hope event updated.',
            'data'    => $event->toAdminArray(),
        ]);
    }

    public function submit(Request $request, HopeEvent $hopeEvent): JsonResponse
    {
        return $this->workflowSubmit($request, $hopeEvent);
    }

    public function reject(RejectContentRequest $request, HopeEvent $hopeEvent): JsonResponse
    {
        return $this->workflowReject($request, $hopeEvent);
    }

    public function publish(Request $request, HopeEvent $hopeEvent): JsonResponse
    {
        return $this->workflowPublish($request, $hopeEvent);
    }

    public function destroy(HopeEvent $hopeEvent): JsonResponse
    {
        $this->hopeEventService->delete($hopeEvent);

        return response()->json([
            'status'  => true,
            'message' => 'Hope event deleted.',
        ]);
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function collectSpeakerPhotos(Request $request): array
    {
        $files = [];
        $photos = $request->file('speaker_photos');
        if (is_array($photos)) {
            foreach ($photos as $index => $file) {
                if ($file) {
                    $files[(int) $index] = $file;
                }
            }
        }

        return $files;
    }
}
