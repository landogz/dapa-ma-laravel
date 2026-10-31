<?php

namespace App\Models;

use App\Models\Concerns\HasContentWorkflow;
use App\Support\AppOptionGroup;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppOption extends Model
{
    use HasContentWorkflow;

    /**
     * Publishers can browse live lookup catalogs and pending review items.
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
            return $this->isWorkflowPublished()
                || $this->isWorkflowPending()
                || $this->isRevisionAwaitingPublisher();
        }

        return true;
    }

    protected $fillable = [
        'group',
        'key',
        'label_en',
        'label_tl',
        'subtitle_en',
        'subtitle_tl',
        'icon',
        'accent_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @param  list<string>  $legacy
     */
    public static function isAllowed(string $group, ?string $key, array $legacy = []): bool
    {
        if ($key === null || $key === '') {
            return true;
        }

        if (in_array($key, $legacy, true)) {
            return true;
        }

        foreach (AppOptionGroup::seedRows() as $row) {
            if ($row['group'] === $group && $row['key'] === $key) {
                return true;
            }
        }

        if (! Schema::hasTable('app_options')) {
            return false;
        }

        try {
            return static::query()
                ->where('group', $group)
                ->where('key', $key)
                ->where('is_active', true)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $meta = AppOptionGroup::meta()[$this->group] ?? [
            'title' => $this->group,
            'selection' => AppOptionGroup::SELECTION_SINGLE,
            'surface' => '',
        ];

        return [
            'id' => $this->id,
            'group' => $this->group,
            'group_title' => $meta['title'],
            'selection' => $meta['selection'],
            'surface' => $meta['surface'] ?? '',
            'key' => $this->key,
            'label_en' => $this->label_en,
            'label_tl' => $this->label_tl,
            'subtitle_en' => $this->subtitle_en,
            'subtitle_tl' => $this->subtitle_tl,
            'icon' => $this->icon,
            'accent_color' => $this->accent_color,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'workflow_status' => $this->workflow_status,
            'revision_status' => $this->revision_status,
            'review_notes' => $this->review_notes,
            'pending_revision' => $this->pending_revision,
            'authored_by' => $this->authored_by,
            'submitted_at' => optional($this->submitted_at)?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => optional($this->reviewed_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(string $locale = 'en'): array
    {
        $isTl = in_array($locale, ['tl', 'fil'], true);

        return [
            'key' => $this->key,
            'label' => $isTl ? $this->label_tl : $this->label_en,
            'label_en' => $this->label_en,
            'label_tl' => $this->label_tl,
            'subtitle' => $isTl ? ($this->subtitle_tl ?? '') : ($this->subtitle_en ?? ''),
            'subtitle_en' => $this->subtitle_en,
            'subtitle_tl' => $this->subtitle_tl,
            'icon' => $this->icon,
            'accent' => $this->accent_color,
            'sort_order' => $this->sort_order,
        ];
    }
}
