<?php

namespace App\Services;

use App\Models\DiaryEntry;
use App\Models\User;
use App\Repositories\DiaryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
        $existing = $this->diaryRepository->findForUserOnDate($user, $date);

        $payload = [
            'title'     => $data['title'] ?? null,
            'sky'       => $data['sky'] ?? null,
            'feelings'  => $this->normalizeFeelings($data['feelings'] ?? null),
            'impact'    => $data['impact'] ?? null,
            'gratitude' => $this->nullableTrim($data['gratitude'] ?? null),
            'body_html' => $this->nullableTrim($data['body_html'] ?? null),
        ];

        // One entry per day: update today's draft/entry instead of failing.
        if ($existing !== null) {
            $this->applyImagePayload($existing, $data, $payload);

            return $this->diaryRepository->update($existing, $payload);
        }

        $this->applyImagePayload(null, $data, $payload);

        return $this->diaryRepository->create($user, [
            'entry_date' => $date->toDateString(),
            ...$payload,
        ]);
    }

    public function update(User $user, int $id, array $data): DiaryEntry
    {
        $entry = $this->diaryRepository->findForUserOrFail($user, $id);

        $payload = [];
        foreach (['title', 'sky', 'impact'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        if (array_key_exists('feelings', $data)) {
            $payload['feelings'] = $this->normalizeFeelings($data['feelings']);
        }
        if (array_key_exists('gratitude', $data)) {
            $payload['gratitude'] = $this->nullableTrim($data['gratitude']);
        }
        if (array_key_exists('body_html', $data)) {
            $payload['body_html'] = $this->nullableTrim($data['body_html']);
        }

        $this->applyImagePayload($entry, $data, $payload);

        return $this->diaryRepository->update($entry, $payload);
    }

    public function delete(User $user, int $id): void
    {
        $entry = $this->diaryRepository->findForUserOrFail($user, $id);
        $this->deleteStoredImage($entry->image_path);
        $this->diaryRepository->delete($entry);
    }

    public function formatEntry(DiaryEntry $entry): array
    {
        return [
            'id'          => $entry->id,
            'entry_date'  => $entry->entry_date?->toDateString(),
            'title'       => $entry->title,
            'sky'         => $entry->sky,
            'feelings'    => $entry->feelings ?? [],
            'impact'      => $entry->impact,
            'gratitude'   => $entry->gratitude,
            'body_html'   => $entry->body_html,
            'image_url'   => $this->publicImageUrl($entry->image_path),
            'created_at'  => $entry->created_at?->toIso8601String(),
            'updated_at'  => $entry->updated_at?->toIso8601String(),
        ];
    }

    public function listAdmin(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->diaryRepository->paginateAdmin($perPage, $filters);
    }

    /**
     * @return list<array{id:int,name:string,email:?string,entries_count:int}>
     */
    public function listAdminUsers(): array
    {
        return $this->diaryRepository->listUsersWithEntries();
    }

    public function showAdmin(int $id): DiaryEntry
    {
        return $this->diaryRepository->findOrFail($id);
    }

    public function deleteAdmin(int $id): void
    {
        $entry = $this->diaryRepository->findOrFail($id);
        $this->deleteStoredImage($entry->image_path);
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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function applyImagePayload(?DiaryEntry $entry, array $data, array &$payload): void
    {
        $image = $data['image'] ?? null;
        $remove = (bool) ($data['remove_image'] ?? false);

        if ($image instanceof UploadedFile) {
            $path = $image->store('diary-images', 'public');
            if ($entry?->image_path) {
                $this->deleteStoredImage($entry->image_path);
            }
            $payload['image_path'] = $path;

            return;
        }

        if ($remove) {
            if ($entry?->image_path) {
                $this->deleteStoredImage($entry->image_path);
            }
            $payload['image_path'] = null;
        }
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function publicImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function normalizeFeelings(mixed $feelings): ?array
    {
        if (!is_array($feelings)) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(
            array_map(static fn ($f) => trim((string) $f), $feelings),
            static fn ($f) => $f !== ''
        )));

        return $normalized === [] ? null : $normalized;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
