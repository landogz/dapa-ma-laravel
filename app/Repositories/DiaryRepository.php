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

    public function paginateAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return DiaryEntry::query()
            ->with(['user:id,name,email'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($perPage);
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
