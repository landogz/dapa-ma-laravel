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
        if (! method_exists($model, 'isVisibleToAdmin') || $model->isVisibleToAdmin($request->user())) {
            return;
        }

        $alreadyResolved = method_exists($model, 'isWorkflowPublished')
            && $model->isWorkflowPublished()
            && method_exists($model, 'hasPendingRevision')
            && ! $model->hasPendingRevision();
        $returnedForRevision = (method_exists($model, 'isWorkflowDraft') && $model->isWorkflowDraft())
            || (method_exists($model, 'isRevisionDraft') && $model->isRevisionDraft());

        abort(response()->json([
            'status' => false,
            'code' => $alreadyResolved ? 'review_resolved' : ($returnedForRevision ? 'review_returned' : 'not_in_queue'),
            'message' => $alreadyResolved
                ? 'This update was already published. It is no longer in your review queue.'
                : ($returnedForRevision
                    ? 'This item was returned for revision. It will come back to your queue when the editor resubmits it.'
                    : 'This item is not available in your publisher review queue.'),
        ], ($alreadyResolved || $returnedForRevision) ? 409 : 404));
    }

    public function submit(Request $request, Model $model): JsonResponse
    {
        $wasPublishedUpdate = method_exists($model, 'isRevisionDraft') && $model->isRevisionDraft();
        $isSuperAdmin = $request->user()?->role === 'super_admin';

        try {
            $updated = $this->contentWorkflowService()->submit(
                $model,
                $request->user(),
                $this->contentWorkflowSection(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        if ($isSuperAdmin) {
            return response()->json([
                'status' => true,
                'message' => $wasPublishedUpdate
                    ? 'Update published. The live version is now updated.'
                    : 'Published successfully.',
                'data' => $updated,
            ]);
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

    public function archive(Request $request, Model $model): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $model);

        try {
            $updated = $this->contentWorkflowService()->archive(
                $model,
                $request->user(),
                $this->contentWorkflowSection(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Archived. It is hidden from the mobile app until restored.',
            'data' => $updated,
        ]);
    }

    public function restore(Request $request, Model $model): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $model);

        try {
            $updated = $this->contentWorkflowService()->restore(
                $model,
                $request->user(),
                $this->contentWorkflowSection(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        $editorRestore = $request->user()?->role === 'editor';

        return response()->json([
            'status' => true,
            'message' => $editorRestore
                ? 'Restore submitted for publisher review. It stays hidden until they publish it.'
                : 'Restored and published.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Model $model): JsonResponse
    {
        $this->assertVisibleToAdmin($request, $model);

        try {
            $result = $this->contentWorkflowService()->deleteDraft(
                $model,
                $request->user(),
                fn (Model $record) => $this->permanentlyDeleteDraft($record),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        if (($result['mode'] ?? null) === 'discarded') {
            return response()->json([
                'status' => true,
                'message' => 'Draft update discarded. The live published version is unchanged.',
                'data' => $result['model'],
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Draft deleted.',
            'data' => null,
        ]);
    }

    protected function permanentlyDeleteDraft(Model $model): void
    {
        $model->delete();
    }
}
