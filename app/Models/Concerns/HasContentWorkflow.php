<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasContentWorkflow
{
    public function initializeHasContentWorkflow(): void
    {
        $this->mergeFillable([
            'workflow_status',
            'review_notes',
            'authored_by',
            'submitted_at',
            'reviewed_by',
            'reviewed_at',
            'pending_revision',
            'revision_status',
        ]);

        $this->mergeCasts([
            'authored_by' => 'integer',
            'reviewed_by' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'pending_revision' => 'array',
        ]);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->getTable().'.workflow_status', ContentWorkflowStatus::PUBLISHED);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where($this->getTable().'.workflow_status', ContentWorkflowStatus::PENDING);
    }

    /**
     * Publishers only see the review queue (pending new items + published updates awaiting approval).
     */
    public function scopeVisibleInAdminFor(Builder $query, ?User $user): Builder
    {
        if ($user?->role === 'publisher') {
            $table = $this->getTable();

            return $query->where(function (Builder $inner) use ($table): void {
                $inner->where($table.'.workflow_status', ContentWorkflowStatus::PENDING)
                    ->orWhere(function (Builder $revision) use ($table): void {
                        $revision->where($table.'.workflow_status', ContentWorkflowStatus::PUBLISHED)
                            ->where($table.'.revision_status', ContentWorkflowStatus::PENDING);
                    });
            });
        }

        return $query;
    }

    public function isVisibleToAdmin(?User $user): bool
    {
        if ($user?->role === 'publisher') {
            return $this->isWorkflowPending() || $this->isRevisionAwaitingPublisher();
        }

        return true;
    }

    public function isWorkflowDraft(): bool
    {
        return ($this->workflow_status ?? ContentWorkflowStatus::PUBLISHED) === ContentWorkflowStatus::DRAFT;
    }

    public function isWorkflowPending(): bool
    {
        return ($this->workflow_status ?? null) === ContentWorkflowStatus::PENDING;
    }

    public function isWorkflowPublished(): bool
    {
        return ($this->workflow_status ?? ContentWorkflowStatus::PUBLISHED) === ContentWorkflowStatus::PUBLISHED;
    }

    public function hasPendingRevision(): bool
    {
        return filled($this->revision_status)
            && is_array($this->pending_revision)
            && $this->pending_revision !== [];
    }

    public function isRevisionDraft(): bool
    {
        return $this->isWorkflowPublished() && $this->revision_status === ContentWorkflowStatus::DRAFT;
    }

    public function isRevisionAwaitingPublisher(): bool
    {
        return $this->isWorkflowPublished() && $this->revision_status === ContentWorkflowStatus::PENDING;
    }

    /**
     * Content fields that can be staged in a pending revision without changing the live row.
     *
     * @return list<string>
     */
    public function workflowContentAttributes(): array
    {
        $excluded = [
            'slug',
            'workflow_status',
            'review_notes',
            'authored_by',
            'submitted_at',
            'reviewed_by',
            'reviewed_at',
            'pending_revision',
            'revision_status',
        ];

        return array_values(array_diff($this->getFillable(), $excluded));
    }

    /**
     * @return array<string, mixed>
     */
    public function liveWorkflowContentSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->workflowContentAttributes() as $attribute) {
            $snapshot[$attribute] = $this->getAttribute($attribute);
        }

        return $snapshot;
    }

    public function workflowTitle(): string
    {
        $revisionTitle = is_array($this->pending_revision)
            ? ($this->pending_revision['title']
                ?? $this->pending_revision['title_en']
                ?? $this->pending_revision['name']
                ?? $this->pending_revision['message_en']
                ?? null)
            : null;

        if (is_string($revisionTitle) && $revisionTitle !== '') {
            return $revisionTitle;
        }

        foreach (['title', 'title_en', 'name', 'question_en', 'message_en', 'key', 'word', 'slug'] as $field) {
            $value = $this->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }
}
