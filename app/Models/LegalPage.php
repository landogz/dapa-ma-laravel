<?php

namespace App\Models;

use App\Models\Concerns\HasContentWorkflow;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    use HasContentWorkflow;

    protected $fillable = [
        'slug',
        'title_en',
        'title_tl',
        'subtitle_en',
        'subtitle_tl',
        'intro_en',
        'intro_tl',
        'body_en',
        'body_tl',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Fixed settings pages: publishers can always open live pages (to review updates),
     * not only the pending-review queue used by other workflow modules.
     */
    public function scopeVisibleInAdminFor(Builder $query, ?User $user): Builder
    {
        if ($user?->role === 'publisher') {
            return $query->whereIn($this->getTable().'.workflow_status', [
                ContentWorkflowStatus::PUBLISHED,
                ContentWorkflowStatus::PENDING,
            ]);
        }

        return $query;
    }

    public function isVisibleToAdmin(?User $user): bool
    {
        if ($user?->role === 'publisher') {
            return $this->isWorkflowPublished() || $this->isWorkflowPending();
        }

        return true;
    }

    public function toPublicArray(string $locale = 'en'): array
    {
        $locale = in_array($locale, ['tl', 'fil'], true) ? 'tl' : 'en';
        $isTl = $locale === 'tl';

        return [
            'slug' => $this->slug,
            'locale' => $locale,
            'title' => $isTl ? $this->title_tl : $this->title_en,
            'subtitle' => $isTl ? ($this->subtitle_tl ?? '') : ($this->subtitle_en ?? ''),
            'intro' => $isTl ? ($this->intro_tl ?? '') : ($this->intro_en ?? ''),
            'body' => $isTl ? $this->body_tl : $this->body_en,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
