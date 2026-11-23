<?php

namespace App\Repositories;

use App\Models\RelaxingMusicTrack;
use App\Models\User;
use Illuminate\Support\Collection;

class RelaxingMusicTrackRepository
{
    /**
     * @param  array{search?: ?string, is_active?: bool|null, workflow_status?: ?string}  $filters
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function listAdmin(array $filters = [], ?User $actor = null): Collection
    {
        return RelaxingMusicTrack::query()
            ->visibleInAdminFor($actor)
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($q) use ($like): void {
                    $q->where('title', 'like', $like)
                        ->orWhere('title_tl', 'like', $like);
                });
            })
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, function ($query) use ($filters): void {
                $query->where('is_active', (bool) $filters['is_active']);
            })
            ->when($filters['workflow_status'] ?? null, function ($query, $status): void {
                $query->where('workflow_status', $status);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Public Calm Corner list — published and active only.
     *
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function listPublishedActive(): Collection
    {
        return RelaxingMusicTrack::query()
            ->published()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int $id): RelaxingMusicTrack
    {
        return RelaxingMusicTrack::query()->findOrFail($id);
    }

    public function nextSortOrder(): int
    {
        return (int) RelaxingMusicTrack::query()->max('sort_order') + 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): RelaxingMusicTrack
    {
        return RelaxingMusicTrack::query()->create($payload)->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(RelaxingMusicTrack $track, array $payload): RelaxingMusicTrack
    {
        $track->update($payload);

        return $track->fresh();
    }

    public function delete(RelaxingMusicTrack $track): void
    {
        $track->delete();
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(?User $actor = null): array
    {
        $base = RelaxingMusicTrack::query()->visibleInAdminFor($actor);

        return [
            '' => (clone $base)->where('workflow_status', '!=', 'archived')->count(),
            'draft' => (clone $base)->where('workflow_status', 'draft')->count(),
            'pending' => (clone $base)->where(function ($q): void {
                $q->where('workflow_status', 'pending')
                    ->orWhere(function ($inner): void {
                        $inner->where('workflow_status', 'published')
                            ->where('revision_status', 'pending');
                    });
            })->count(),
            'published' => (clone $base)->where('workflow_status', 'published')->count(),
            'archived' => (clone $base)->where('workflow_status', 'archived')->count(),
            '1' => (clone $base)->where('is_active', true)->count(),
            '0' => (clone $base)->where('is_active', false)->count(),
        ];
    }
}
