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

    public function submit(Request $request, Model $model): JsonResponse
    {
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
            'message' => 'Submitted for publisher review.',
            'data' => $updated,
        ]);
    }

    public function reject(RejectContentRequest $request, Model $model): JsonResponse
    {
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
            'message' => 'Returned to draft for the editor.',
            'data' => $updated,
        ]);
    }

    public function publish(Request $request, Model $model): JsonResponse
    {
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
            'message' => 'Published successfully.',
            'data' => $updated,
        ]);
    }
}
