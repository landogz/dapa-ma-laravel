<?php

namespace App\Services\ContentWorkflow;

use App\Models\Concerns\HasContentWorkflow;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ContentWorkflowService
{
    public function __construct(
        private readonly AdminNotificationService $adminNotificationService,
    ) {
    }

    /**
     * Force draft ownership fields when an editor (or anyone non–super_admin) creates content.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(User $actor, array $data): array
    {
        if ($actor->role === 'super_admin' && ($data['workflow_status'] ?? null) === ContentWorkflowStatus::PUBLISHED) {
            $data['authored_by'] = $data['authored_by'] ?? $actor->id;
            $data['reviewed_by'] = $actor->id;
            $data['reviewed_at'] = now();
            $data['review_notes'] = null;

            return $data;
        }

        $data['workflow_status'] = ContentWorkflowStatus::DRAFT;
        $data['authored_by'] = $actor->id;
        $data['review_notes'] = null;
        $data['submitted_at'] = null;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;

        return $data;
    }

    /**
     * Editors may only edit draft/pending rows they can see; cannot publish via update.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareUpdatePayload(Model $model, User $actor, array $data): array
    {
        $this->assertUsesWorkflow($model);

        if ($actor->role === 'publisher') {
            throw new InvalidArgumentException('Publishers cannot edit content. Publish or reject instead.');
        }

        if ($actor->role === 'editor') {
            if (! in_array($model->workflow_status, [ContentWorkflowStatus::DRAFT, ContentWorkflowStatus::PENDING], true)) {
                throw new InvalidArgumentException('Editors can only edit draft or pending items.');
            }

            unset($data['workflow_status']);
            // Re-editing a rejected or pending item returns it to draft until re-submitted.
            if ($model->workflow_status === ContentWorkflowStatus::PENDING) {
                $data['workflow_status'] = ContentWorkflowStatus::DRAFT;
                $data['submitted_at'] = null;
            }
        }

        if ($actor->role !== 'super_admin') {
            unset($data['authored_by'], $data['reviewed_by'], $data['reviewed_at']);
        }

        return $data;
    }

    public function submit(Model $model, User $editor, string $section): Model
    {
        $this->assertUsesWorkflow($model);

        if (! in_array($editor->role, ['editor', 'super_admin'], true)) {
            abort(403, 'Only editors can submit for review.');
        }

        if ($model->workflow_status === ContentWorkflowStatus::PUBLISHED && $editor->role !== 'super_admin') {
            throw new InvalidArgumentException('Published items cannot be submitted.');
        }

        $model->forceFill([
            'workflow_status' => ContentWorkflowStatus::PENDING,
            'review_notes' => null,
            'authored_by' => $model->authored_by ?: $editor->id,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        $this->adminNotificationService->notifyContentSubmitted(
            $section,
            $model,
            $editor,
        );

        return $model->fresh();
    }

    public function reject(Model $model, User $publisher, string $section, string $reviewNotes): Model
    {
        $this->assertUsesWorkflow($model);

        if (! in_array($publisher->role, ['publisher', 'super_admin'], true)) {
            abort(403, 'Only publishers can reject submissions.');
        }

        if ($model->workflow_status !== ContentWorkflowStatus::PENDING) {
            throw new InvalidArgumentException('Only pending items can be rejected.');
        }

        $model->forceFill([
            'workflow_status' => ContentWorkflowStatus::DRAFT,
            'review_notes' => $reviewNotes,
            'reviewed_by' => $publisher->id,
            'reviewed_at' => now(),
            'submitted_at' => null,
        ])->save();

        $this->adminNotificationService->notifyContentRejected(
            $section,
            $model,
            $publisher,
            $reviewNotes,
        );

        return $model->fresh();
    }

    public function publish(Model $model, User $publisher, string $section): Model
    {
        $this->assertUsesWorkflow($model);

        if (! in_array($publisher->role, ['publisher', 'super_admin'], true)) {
            abort(403, 'Only publishers can publish.');
        }

        if (! in_array($model->workflow_status, [ContentWorkflowStatus::PENDING, ContentWorkflowStatus::DRAFT], true)
            && $publisher->role !== 'super_admin') {
            throw new InvalidArgumentException('Only pending items can be published.');
        }

        // Publishers publish from pending; super_admin may publish drafts too.
        if ($publisher->role === 'publisher' && $model->workflow_status !== ContentWorkflowStatus::PENDING) {
            throw new InvalidArgumentException('Only pending items can be published.');
        }

        $model->forceFill([
            'workflow_status' => ContentWorkflowStatus::PUBLISHED,
            'review_notes' => null,
            'reviewed_by' => $publisher->id,
            'reviewed_at' => now(),
        ])->save();

        $this->adminNotificationService->notifyContentPublished(
            $section,
            $model,
            $publisher,
        );

        return $model->fresh();
    }

    private function assertUsesWorkflow(Model $model): void
    {
        $uses = in_array(HasContentWorkflow::class, class_uses_recursive($model), true);

        if (! $uses) {
            throw new InvalidArgumentException('Model does not support content workflow.');
        }
    }
}
