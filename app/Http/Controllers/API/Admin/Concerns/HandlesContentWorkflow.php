<?php

namespace App\Http\Controllers\API\Admin\Concerns;

use App\Http\Requests\Admin\RejectContentRequest;
use App\Services\ContentWorkflow\ContentWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

trait HandlesContentWorkflow
{
    abstract protected function contentWorkflowSection(): string;

    abstract protected function contentWorkflowService(): ContentWorkflowService;

    protected function assertVisibleToAdmin(Request $request, Model $model): void
    {
        if (method_exists($model, 'isVisibleToAdmin') && ! $model->isVisibleToAdmin($request->user())) {
            abort(404);
        }
    }

    public function submit(Request $request, Model $model): JsonResponse
    {
        $wasPublishedUpdate = method_exists($model, 'isRevisionDraft') && $model->isRevisionDraft();

        try {
            $updated = $this->contentWorkflowService()->submit(
                $model,
                $request->user(),
                $this->contentWorkflowSection(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => $wasPublishedUpdate
                ? 'Update submitted for publisher review. The live version stays unchanged until approved.'
                : 'Submitted for publisher review.',
            'data' => $updated,
        ]);
    }

    public function reject(RejectContentRequest $request, Model $model): JsonResponse
    {
        $wasPublishedUpdate = method_exists($model, 'isRevisionAwaitingPublisher')
            && $model->isRevisionAwaitingPublisher();

        try {
            $updated = $this->contentWorkflowService()->reject(
                $model,
                $request->user(),
                $this->contentWorkflowSection(),
                $request->validated('review_notes'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => $wasPublishedUpdate
                ? 'Update returned for revision. The live published version is unchanged.'
                : 'Returned to draft for the editor.',
            'data' => $updated,
        ]);
    }

    public function publish(Request $request, Model $model): JsonResponse
    {
        $wasPublishedUpdate = method_exists($model, 'isRevisionAwaitingPublisher')
            && $model->isRevisionAwaitingPublisher();

        try {
            $updated = $this->contentWorkflowService()->publish(
                $model,
                $request->user(),
                $this->contentWorkflowSection(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => $wasPublishedUpdate
                ? 'Update approved. The live version is now updated.'
                : 'Published successfully.',
            'data' => $updated,
        ]);
    }
}
