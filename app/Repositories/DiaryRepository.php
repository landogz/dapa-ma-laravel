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
            'body_html'  => $data['body_html'],
        ]);
    }

    public function update(DiaryEntry $entry, array $data): DiaryEntry
    {
        $entry->fill([
            'title'     => $data['title'] ?? $entry->title,
            'body_html' => $data['body_html'] ?? $entry->body_html,
        ]);
        $entry->save();

        return $entry->fresh();
    }

    public function delete(DiaryEntry $entry): void
    {
        $entry->delete();
    }
}
