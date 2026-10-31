<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreAppTranslationRequest;
use App\Http\Requests\Admin\UpdateAppTranslationRequest;
use App\Models\AppTranslation;
use App\Services\AppTranslationService;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Support\AppTranslation\Visual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppTranslationAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        archive as protected workflowArchive;
        restore as protected workflowRestore;
    }

    public function __construct(
        private readonly AppTranslationService $appTranslationService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'app-translations';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(Request $request): JsonResponse
    {
        $active = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $group = $request->string('group')->toString() ?: null;
        $visual = $request->string('visual')->toString() ?: null;
        $keys = null;

        if ($group === 'bottom_menu' || $visual === 'menu') {
            $group = null;
            $keys = Visual::MENU_KEYS;
        } elseif ($visual === 'card') {
            $group = null;
            $keys = Visual::CARD_KEYS;
        }

        $counts = $this->appTranslationService->groupCounts();
        $counts['bottom_menu'] = AppTranslation::query()
            ->whereIn('key', Visual::MENU_KEYS)
            ->count();

        return response()->json([
            'status' => true,
            'data' => $this->appTranslationService->list(
                min(2000, max(1, (int) $request->integer('per_page', 200))),
                $request->string('search')->toString() ?: null,
                $group,
                $active,
                $keys,
                $request->user(),
            )->through(fn (AppTranslation $row) => Visual::formatAdmin($row)),
            'meta' => [
                'groups' => $this->appTranslationService->groups()->values(),
                'counts' => $counts,
                'icons' => Visual::iconChoices(),
                'card_keys' => Visual::visualKeys(),
                'menu_keys' => Visual::MENU_KEYS,
                'logo_keys' => Visual::LOGO_KEYS,
                'page_keys' => Visual::PAGE_KEYS,
            ],
        ]);
    }

    public function show(AppTranslation $appTranslation): JsonResponse
    {
        $this->assertVisibleToAdmin(request(), $appTranslation);

        return response()->json([
            'status' => true,
            'data' => Visual::formatAdmin($appTranslation),
        ]);
    }

    public function store(StoreAppTranslationRequest $request): JsonResponse
    {
        $translation = $this->appTranslationService->create($this->payloadFromRequest($request), $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Translation created.',
            'data' => Visual::formatAdmin($translation),
        ], 201);
    }

    public function update(UpdateAppTranslationRequest $request, AppTranslation $appTranslation): JsonResponse
    {
        $translation = $this->appTranslationService->update(
            $appTranslation,
            $this->payloadFromRequest($request),
            $request->user(),
        );

        return response()->json([
            'status' => true,
            'message' => 'Translation updated.',
            'data' => Visual::formatAdmin($translation),
        ]);
    }

    public function submit(Request $request, AppTranslation $appTranslation): JsonResponse
    {
        return $this->workflowSubmit($request, $appTranslation);
    }

    public function reject(RejectContentRequest $request, AppTranslation $appTranslation): JsonResponse
    {
        return $this->workflowReject($request, $appTranslation);
    }

    public function publish(Request $request, AppTranslation $appTranslation): JsonResponse
    {
        return $this->workflowPublish($request, $appTranslation);
    }

    public function archive(Request $request, AppTranslation $appTranslation): JsonResponse
    {
        return $this->workflowArchive($request, $appTranslation);
    }

    public function restore(Request $request, AppTranslation $appTranslation): JsonResponse
    {
        return $this->workflowRestore($request, $appTranslation);
    }

    public function destroy(AppTranslation $appTranslation): JsonResponse
    {
        $this->appTranslationService->delete($appTranslation);

        return response()->json([
            'status' => true,
            'message' => 'Translation deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(StoreAppTranslationRequest|UpdateAppTranslationRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        return $data;
    }
}
