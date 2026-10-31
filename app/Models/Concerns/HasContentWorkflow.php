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
        ]);

        $this->mergeCasts([
            'authored_by' => 'integer',
            'reviewed_by' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
     * Publishers only see the review queue (pending). Editors/super_admin see all statuses.
     */
    public function scopeVisibleInAdminFor(Builder $query, ?User $user): Builder
    {
        if ($user?->role === 'publisher') {
            return $query->where($this->getTable().'.workflow_status', ContentWorkflowStatus::PENDING);
        }

        return $query;
    }

    public function isVisibleToAdmin(?User $user): bool
    {
        if ($user?->role === 'publisher') {
            return $this->isWorkflowPending();
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

    public function workflowTitle(): string
    {
        foreach (['title', 'name', 'question_en', 'key', 'word', 'slug'] as $field) {
            $value = $this->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }
}
