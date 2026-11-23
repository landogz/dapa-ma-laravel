<?php

namespace App\Repositories;

use App\Models\RelaxingMusicTrack;
use Illuminate\Support\Collection;

class RelaxingMusicTrackRepository
{
    /**
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function list(?string $search = null, bool $activeOnly = false): Collection
    {
        $query = RelaxingMusicTrack::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('title_tl', 'like', $like);
            });
        }

        return $query->get();
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
}
