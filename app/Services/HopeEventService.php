<?php

namespace App\Services;

use App\Models\HopeEvent;
use App\Repositories\HopeEventRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HopeEventService
{
    public function __construct(
        private readonly HopeEventRepository $repository,
    ) {
    }

    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $audience = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $audience, $activeOnly);
    }

    public function show(int $id): HopeEvent
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): HopeEvent
    {
        $payload = $this->normalize($data);
        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $payload['cover_path'] = $data['cover']->store('hope-events', 'public');
        }

        if (array_key_exists('speakers', $payload)) {
            $payload['speakers'] = $this->processSpeakers(
                $payload['speakers'] ?? [],
                $data['speaker_photos'] ?? [],
            );
        }

        return $this->repository->create($payload);
    }

    public function update(HopeEvent $event, array $data): HopeEvent
    {
        $payload = $this->normalize($data, partial: true);

        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $this->deleteCover($event->cover_path);
            $payload['cover_path'] = $data['cover']->store('hope-events', 'public');
        } elseif (!empty($data['remove_cover'])) {
            $this->deleteCover($event->cover_path);
            $payload['cover_path'] = null;
        }

        if (array_key_exists('speakers', $payload)) {
            $payload['speakers'] = $this->processSpeakers(
                $payload['speakers'] ?? [],
                $data['speaker_photos'] ?? [],
                $event->speakers ?? [],
            );
        }

        return $this->repository->update($event, $payload);
    }

    public function delete(HopeEvent $event): void
    {
        $this->deleteCover($event->cover_path);
        $this->deleteSpeakerPhotos($event->speakers ?? []);
        $this->repository->delete($event);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        $fields = [
            'title', 'audience', 'status', 'start_date', 'end_date', 'venue',
            'is_online', 'online_label', 'slots', 'registration_url', 'about_text',
            'for_you_items', 'who_can_join', 'highlights', 'details_text',
            'speakers', 'faqs', 'sort_order', 'is_active',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if ($partial && !array_key_exists($field, $data)) {
                continue;
            }
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $payload[$field] = $data[$field];
        }

        foreach (['for_you_items', 'highlights', 'speakers', 'faqs'] as $jsonField) {
            if (!array_key_exists($jsonField, $payload)) {
                continue;
            }
            if (is_string($payload[$jsonField])) {
                $decoded = json_decode($payload[$jsonField], true);
                $payload[$jsonField] = is_array($decoded) ? $decoded : [];
            }
        }

        if (array_key_exists('is_online', $payload)) {
            $payload['is_online'] = filter_var($payload['is_online'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('slots', $payload) && $payload['slots'] !== null && $payload['slots'] !== '') {
            $payload['slots'] = (int) $payload['slots'];
        }
        if (array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = (int) $payload['sort_order'];
        }
        if (array_key_exists('audience', $payload) && $payload['audience'] === '') {
            $payload['audience'] = null;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $speakers
     * @param  array<int, UploadedFile|null>  $photoFiles
     * @param  list<array<string, mixed>>|null  $previousSpeakers
     * @return list<array{name: string, role: ?string, photo_path?: string}>
     */
    private function processSpeakers(
        array $speakers,
        array $photoFiles = [],
        ?array $previousSpeakers = null,
    ): array {
        $keptPaths = [];
        $result = [];

        foreach (array_values($speakers) as $index => $speaker) {
            if (!is_array($speaker)) {
                continue;
            }

            $name = trim((string) ($speaker['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $role = trim((string) ($speaker['role'] ?? ''));
            $photoPath = isset($speaker['photo_path']) && $speaker['photo_path'] !== ''
                ? (string) $speaker['photo_path']
                : null;
            $removePhoto = filter_var(
                $speaker['remove_photo'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($removePhoto && $photoPath) {
                $this->deleteSpeakerPhoto($photoPath);
                $photoPath = null;
            }

            $upload = $photoFiles[$index] ?? null;
            if ($upload instanceof UploadedFile) {
                if ($photoPath) {
                    $this->deleteSpeakerPhoto($photoPath);
                }
                $photoPath = $upload->store('hope-events/speakers', 'public');
            }

            $item = [
                'name' => $name,
                'role' => $role !== '' ? $role : null,
            ];
            if ($photoPath) {
                $item['photo_path'] = $photoPath;
                $keptPaths[] = $photoPath;
            }

            $result[] = $item;
        }

        if ($previousSpeakers !== null) {
            foreach ($previousSpeakers as $prev) {
                if (!is_array($prev)) {
                    continue;
                }
                $prevPath = isset($prev['photo_path']) ? (string) $prev['photo_path'] : '';
                if ($prevPath !== '' && !in_array($prevPath, $keptPaths, true)) {
                    $this->deleteSpeakerPhoto($prevPath);
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $speakers
     */
    private function deleteSpeakerPhotos(array $speakers): void
    {
        foreach ($speakers as $speaker) {
            if (!is_array($speaker)) {
                continue;
            }
            $path = isset($speaker['photo_path']) ? (string) $speaker['photo_path'] : '';
            if ($path !== '') {
                $this->deleteSpeakerPhoto($path);
            }
        }
    }

    private function deleteSpeakerPhoto(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function deleteCover(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
