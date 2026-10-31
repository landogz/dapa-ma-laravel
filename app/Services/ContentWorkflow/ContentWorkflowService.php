<?php

namespace App\Services\ContentWorkflow;

use App\Models\Concerns\HasContentWorkflow;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\UserNotificationService;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ContentWorkflowService
{
    public function __construct(
        private readonly AdminNotificationService $adminNotificationService,
        private readonly UserNotificationService $userNotificationService,
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
            $data['pending_revision'] = null;
            $data['revision_status'] = null;

            return $data;
        }

        $data['workflow_status'] = ContentWorkflowStatus::DRAFT;
        $data['authored_by'] = $actor->id;
        $data['review_notes'] = null;
        $data['submitted_at'] = null;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;
        $data['pending_revision'] = null;
        $data['revision_status'] = null;

        return $data;
    }

    /**
     * Editors may edit draft/pending rows, or stage published updates without changing the live row.
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

        $isPublished = ($model->workflow_status ?? null) === ContentWorkflowStatus::PUBLISHED
            || (method_exists($model, 'isWorkflowPublished') && $model->isWorkflowPublished());

        if ($actor->role === 'editor' && $isPublished) {
            $this->storePendingRevision($model, $data);

            // Live published columns stay untouched — except operational Active/Inactive,
            // which staff expect to take effect immediately (Lookups, blocked words, etc.).
            return $this->liveOperationalPayload($model, $data);
        }

        if ($actor->role === 'editor') {
            if (! in_array($model->workflow_status, [ContentWorkflowStatus::DRAFT, ContentWorkflowStatus::PENDING], true)) {
                throw new InvalidArgumentException('Editors can only edit draft, pending, or published items.');
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

        unset($data['pending_revision'], $data['revision_status']);

        return $data;
    }

    public function submit(Model $model, User $editor, string $section): Model
    {
        $this->assertUsesWorkflow($model);

        if (! in_array($editor->role, ['editor', 'super_admin'], true)) {
            abort(403, 'Only editors can submit for review.');
        }

        $isPublished = ($model->workflow_status ?? null) === ContentWorkflowStatus::PUBLISHED;

        // Published live rows: stage/submit a pending revision without changing the live payload.
        if ($isPublished) {
            if (! $model->hasPendingRevision()) {
                throw new InvalidArgumentException(
                    'Save your changes first. Published content is updated only after a publisher approves the revision.',
                );
            }

            // Re-submit after revision notes, or first submit of an update draft.
            if (! in_array($model->revision_status, [ContentWorkflowStatus::DRAFT, ContentWorkflowStatus::PENDING], true)) {
                throw new InvalidArgumentException(
                    'Save your changes first. Published content is updated only after a publisher approves the revision.',
                );
            }

            $model->forceFill([
                'revision_status' => ContentWorkflowStatus::PENDING,
                'review_notes' => null,
                'authored_by' => $model->authored_by ?: $editor->id,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            $fresh = $model->fresh();
            $this->adminNotificationService->notifyContentSubmitted($section, $fresh, $editor);

            return $fresh;
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

        if ($model->isRevisionAwaitingPublisher()) {
            $model->forceFill([
                'revision_status' => ContentWorkflowStatus::DRAFT,
                'review_notes' => $reviewNotes,
                'reviewed_by' => $publisher->id,
                'reviewed_at' => now(),
                'submitted_at' => null,
            ])->save();

            $this->adminNotificationService->notifyContentRejected(
                $section,
                $model->fresh(),
                $publisher,
                $reviewNotes,
            );

            return $model->fresh();
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

        if ($model->isRevisionAwaitingPublisher()) {
            return $this->applyPendingRevision($model, $publisher, $section);
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
            'pending_revision' => null,
            'revision_status' => null,
        ])->save();

        $this->adminNotificationService->notifyContentPublished(
            $section,
            $model,
            $publisher,
        );

        $fresh = $model->fresh() ?? $model;
        $this->userNotificationService->notifyPublishedContent($section, $fresh);

        return $fresh;
    }

    /**
     * Fields that toggle live availability without waiting for publisher approval.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function liveOperationalPayload(Model $model, array $data): array
    {
        $payload = [];

        if (array_key_exists('is_active', $data) && $model->isFillable('is_active')) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storePendingRevision(Model $model, array $data): void
    {
        $allowed = $model->workflowContentAttributes();
        $revision = is_array($model->pending_revision) && $model->pending_revision !== []
            ? $model->pending_revision
            : $model->liveWorkflowContentSnapshot();

        foreach ($allowed as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $revision[$attribute] = $data[$attribute];
            }
        }

        $model->forceFill([
            'pending_revision' => $revision,
            'revision_status' => ContentWorkflowStatus::DRAFT,
            'review_notes' => null,
        ])->save();
    }

    private function applyPendingRevision(Model $model, User $publisher, string $section): Model
    {
        $revision = $model->pending_revision;

        if (! is_array($revision) || $revision === []) {
            throw new InvalidArgumentException('This published item has no pending update to approve.');
        }

        $payload = [];
        foreach ($model->workflowContentAttributes() as $attribute) {
            if (array_key_exists($attribute, $revision)) {
                $payload[$attribute] = $revision[$attribute];
            }
        }

        $payload['pending_revision'] = null;
        $payload['revision_status'] = null;
        $payload['review_notes'] = null;
        $payload['reviewed_by'] = $publisher->id;
        $payload['reviewed_at'] = now();

        $model->forceFill($payload)->save();

        $fresh = $model->fresh() ?? $model;

        $this->adminNotificationService->notifyContentPublished(
            $section,
            $fresh,
            $publisher,
        );

        $this->userNotificationService->notifyPublishedContent($section, $fresh);

        return $fresh;
    }

    private function assertUsesWorkflow(Model $model): void
    {
        $uses = in_array(HasContentWorkflow::class, class_uses_recursive($model), true);

        if (! $uses) {
            throw new InvalidArgumentException('Model does not support content workflow.');
        }
    }
}
