<?php

namespace App\Repositories;

use App\Models\Contest;
use App\Models\ContestEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ContestRepository
{
    public function paginateContests(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        ?string $category = null,
        bool $activeOnly = true,
        bool $publicOnly = false,
    ): LengthAwarePaginator {
        return Contest::query()
            ->withCount([
                'entries',
                'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($publicOnly, fn ($q) => $q->whereIn('status', ['open', 'closed', 'completed']))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('theme', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            }))
            ->orderByRaw("FIELD(status, 'open', 'closed', 'completed', 'draft')")
            ->orderByDesc('contest_year')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findContestOrFail(int $id): Contest
    {
        return Contest::query()
            ->withCount([
                'entries',
                'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->findOrFail($id);
    }

    public function createContest(array $data): Contest
    {
        return Contest::create($data);
    }

    public function updateContest(Contest $contest, array $data): Contest
    {
        $contest->update($data);

        return $contest->fresh()->loadCount([
            'entries',
            'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);
    }

    public function deleteContest(Contest $contest): void
    {
        $contest->delete();
    }

    public function publishedEntries(Contest $contest): Collection
    {
        return $contest->entries()
            ->with(['user:id,name'])
            ->whereIn('status', ['approved', 'finalist', 'winner'])
            ->orderByRaw("FIELD(status, 'winner', 'finalist', 'approved')")
            ->orderBy('title')
            ->get();
    }

    public function paginateEntries(
        Contest $contest,
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

    public function findUserEntry(Contest $contest, int $userId): ?ContestEntry
    {
        return $contest->entries()
            ->where('user_id', $userId)
            ->first();
    }

    public function createEntry(array $data): ContestEntry
    {
        return ContestEntry::create($data);
    }

    public function updateEntry(ContestEntry $entry, array $data): ContestEntry
    {
        $entry->update($data);

        return $entry->fresh()->load(['user:id,name,email', 'contest', 'reviewer:id,name']);
    }

    public function findEntryOrFail(int $id): ContestEntry
    {
        return ContestEntry::query()
            ->with(['user:id,name,email', 'contest', 'reviewer:id,name'])
            ->findOrFail($id);
    }

    public function clearWinners(Contest $contest): void
    {
        $contest->entries()
            ->where('status', 'winner')
            ->update(['status' => 'finalist']);
    }
}
