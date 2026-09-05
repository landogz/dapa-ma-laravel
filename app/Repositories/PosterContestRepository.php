<?php

namespace App\Repositories;

use App\Models\PosterContest;
use App\Models\PosterContestEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PosterContestRepository
{
    public function paginateContests(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        bool $activeOnly = true,
        bool $publicOnly = false,
    ): LengthAwarePaginator {
        return PosterContest::query()
            ->withCount([
                'entries',
                'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($publicOnly, fn ($q) => $q->whereIn('status', ['open', 'closed', 'completed']))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('theme', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->orderByRaw("FIELD(status, 'open', 'closed', 'completed', 'draft')")
            ->orderByDesc('contest_year')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findContestOrFail(int $id): PosterContest
    {
        return PosterContest::query()
            ->withCount([
                'entries',
                'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->findOrFail($id);
    }

    public function createContest(array $data): PosterContest
    {
        return PosterContest::create($data);
    }

    public function updateContest(PosterContest $contest, array $data): PosterContest
    {
        $contest->update($data);

        return $contest->fresh()->loadCount([
            'entries',
            'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);
    }

    public function deleteContest(PosterContest $contest): void
    {
        $contest->delete();
    }

    public function publishedEntries(PosterContest $contest): Collection
    {
        return $contest->entries()
            ->with(['user:id,name'])
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->orderByRaw("FIELD(status, 'winner', 'finalist', 'approved')")
            ->orderBy('title')
            ->get();
    }

    public function paginateEntries(
        PosterContest $contest,
        int $perPage = 50,
        ?string $status = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $contest->entries()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('creator_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
            }))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'finalist', 'winner', 'rejected')")
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findEntryOrFail(int $id): PosterContestEntry
    {
        return PosterContestEntry::query()
            ->with(['user:id,name,email', 'contest', 'reviewer:id,name'])
            ->findOrFail($id);
    }

    public function findUserEntry(PosterContest $contest, int $userId): ?PosterContestEntry
    {
        return $contest->entries()
            ->where('user_id', $userId)
            ->first();
    }

    public function createEntry(array $data): PosterContestEntry
    {
        return PosterContestEntry::create($data);
    }

    public function updateEntry(PosterContestEntry $entry, array $data): PosterContestEntry
    {
        $entry->update($data);

        return $entry->fresh()->load(['user:id,name,email', 'contest', 'reviewer:id,name']);
    }

    public function clearWinners(PosterContest $contest): void
    {
        $contest->entries()
            ->where('status', 'winner')
            ->update(['status' => 'finalist']);
    }
}
