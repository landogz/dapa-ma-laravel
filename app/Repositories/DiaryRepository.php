<?php

namespace App\Repositories;

use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DiaryRepository
{
    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return DiaryEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('entry_date')
            ->paginate($perPage);
    }

    public function paginateAdmin(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = DiaryEntry::query()
            ->with(['user:id,name,email'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : 0;
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $sky = isset($filters['sky']) ? trim((string) $filters['sky']) : '';
        if ($sky !== '') {
            $query->where('sky', $sky);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('body_html', 'like', $like)
                    ->orWhere('gratitude', 'like', $like)
                    ->orWhere('sky', 'like', $like)
                    ->orWhere('impact', 'like', $like)
                    ->orWhereHas('user', function ($userQuery) use ($like): void {
                        $userQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('nickname', 'like', $like);
                    });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Users who have at least one journal entry (for admin filter).
     *
     * @return list<array{id:int,name:string,email:?string,entries_count:int}>
     */
    public function listUsersWithEntries(): array
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->selectRaw('COUNT(diary_entries.id) as entries_count')
            ->join('diary_entries', 'diary_entries.user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => $user->email,
                'entries_count' => (int) ($user->entries_count ?? 0),
            ])
            ->all();
    }

    public function findOrFail(int $id): DiaryEntry
    {
        return DiaryEntry::query()
            ->with(['user:id,name,email'])
            ->findOrFail($id);
    }

    public function findForUserOnDate(User $user, Carbon $date): ?DiaryEntry
    {
        return DiaryEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('entry_date', $date->toDateString())
            ->first();
    }

    public function findForUserOrFail(User $user, int $id): DiaryEntry
    {
        return DiaryEntry::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);
    }

    public function create(User $user, array $data): DiaryEntry
    {
        return DiaryEntry::query()->create([
            'user_id'    => $user->id,
            'entry_date' => $data['entry_date'],
            'title'      => $data['title'] ?? null,
            'sky'        => $data['sky'] ?? null,
            'feelings'   => $data['feelings'] ?? null,
            'impact'     => $data['impact'] ?? null,
            'gratitude'  => $data['gratitude'] ?? null,
            'body_html'  => $data['body_html'] ?? null,
            'image_path' => $data['image_path'] ?? null,
        ]);
    }

    public function update(DiaryEntry $entry, array $data): DiaryEntry
    {
        $payload = [];

        foreach (['title', 'sky', 'feelings', 'impact', 'gratitude', 'body_html', 'image_path'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($payload !== []) {
            $entry->fill($payload);
            $entry->save();
        }

        return $entry->fresh();
    }

    public function delete(DiaryEntry $entry): void
    {
        $entry->delete();
    }
}
