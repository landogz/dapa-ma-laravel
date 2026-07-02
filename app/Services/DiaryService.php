<?php

namespace App\Services;

use App\Models\DiaryEntry;
use App\Models\User;
use App\Repositories\DiaryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DiaryService
{
    public function __construct(
        private readonly DiaryRepository $diaryRepository,
    ) {
    }

    public function list(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->diaryRepository->paginateForUser($user, $perPage);
    }

    public function getToday(User $user): ?DiaryEntry
    {
        return $this->diaryRepository->findForUserOnDate($user, Carbon::today());
    }

    public function show(User $user, int $id): DiaryEntry
    {
        return $this->diaryRepository->findForUserOrFail($user, $id);
    }

    public function store(User $user, array $data): DiaryEntry
    {
        $date = Carbon::parse($data['entry_date'])->startOfDay();

        if ($this->diaryRepository->findForUserOnDate($user, $date)) {
            throw ValidationException::withMessages([
                'entry_date' => ['You already have a diary entry for this date.'],
            ]);
        }

        return $this->diaryRepository->create($user, [
            'entry_date' => $date->toDateString(),
            'title'      => $data['title'] ?? null,
            'body_html'  => $data['body_html'],
        ]);
    }

    public function update(User $user, int $id, array $data): DiaryEntry
    {
        $entry = $this->diaryRepository->findForUserOrFail($user, $id);

        return $this->diaryRepository->update($entry, $data);
    }

    public function delete(User $user, int $id): void
    {
        $entry = $this->diaryRepository->findForUserOrFail($user, $id);
        $this->diaryRepository->delete($entry);
    }

    public function formatEntry(DiaryEntry $entry): array
    {
        return [
            'id'          => $entry->id,
            'entry_date'  => $entry->entry_date?->toDateString(),
            'title'       => $entry->title,
            'body_html'   => $entry->body_html,
            'created_at'  => $entry->created_at?->toIso8601String(),
            'updated_at'  => $entry->updated_at?->toIso8601String(),
        ];
    }

    public function listAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return $this->diaryRepository->paginateAdmin($perPage);
    }

    public function showAdmin(int $id): DiaryEntry
    {
        return $this->diaryRepository->findOrFail($id);
    }

    public function deleteAdmin(int $id): void
    {
        $entry = $this->diaryRepository->findOrFail($id);
        $this->diaryRepository->delete($entry);
    }

    public function formatAdminEntry(DiaryEntry $entry): array
    {
        return [
            ...$this->formatEntry($entry),
            'user' => [
                'id'    => $entry->user_id,
                'name'  => $entry->user?->name,
                'email' => $entry->user?->email,
            ],
        ];
    }
}
