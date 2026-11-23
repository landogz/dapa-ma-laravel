<?php

namespace App\Services;

use App\Models\RelaxingMusicTrack;
use App\Repositories\RelaxingMusicTrackRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RelaxingMusicService
{
    public function __construct(
        private readonly RelaxingMusicTrackRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function list(?string $search = null, bool $activeOnly = false): Collection
    {
        return $this->repository->list($search, $activeOnly);
    }

    /**
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function publicList(): Collection
    {
        return $this->repository->list(null, true);
    }

    public function find(int $id): RelaxingMusicTrack
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RelaxingMusicTrack
    {
        $payload = $this->normalize($data);

        if (! (($data['audio'] ?? null) instanceof UploadedFile)) {
            throw new \InvalidArgumentException('Audio file is required.');
        }

        $payload['audio_path'] = $data['audio']->store('relaxing-music', 'public');

        if (! array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = $this->repository->nextSortOrder();
        }

        return $this->repository->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<RelaxingMusicTrack>
     */
    public function createMany(array $data): array
    {
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            $data['audios'] ?? (($data['audio'] ?? null) instanceof UploadedFile ? [$data['audio']] : []),
            static fn ($file) => $file instanceof UploadedFile,
        ));

        if ($files === []) {
            throw new \InvalidArgumentException('At least one audio file is required.');
        }

        $nextSort = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== ''
            ? (int) $data['sort_order']
            : $this->repository->nextSortOrder();

        $created = [];

        foreach ($files as $index => $file) {
            $title = null;
            if (count($files) === 1 && filled($data['title'] ?? null)) {
                $title = (string) $data['title'];
            } else {
                $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
                $title = filled($original) ? $original : 'Relaxing track';
            }

            $created[] = $this->create([
                'title' => $title,
                'title_tl' => count($files) === 1 ? ($data['title_tl'] ?? null) : null,
                'audio' => $file,
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $nextSort + $index,
            ]);
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RelaxingMusicTrack $track, array $data): RelaxingMusicTrack
    {
        $payload = $this->normalize($data, partial: true);

        if (($data['audio'] ?? null) instanceof UploadedFile) {
            $this->deleteFile($track->audio_path);
            $payload['audio_path'] = $data['audio']->store('relaxing-music', 'public');
        }

        return $this->repository->update($track, $payload);
    }

    public function delete(RelaxingMusicTrack $track): void
    {
        $this->deleteFile($track->audio_path);
        $this->repository->delete($track);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        $payload = [];

        foreach (['title', 'title_tl', 'duration_seconds', 'is_active', 'sort_order'] as $key) {
            if (! array_key_exists($key, $data)) {
                if ($partial) {
                    continue;
                }
                if ($key === 'is_active') {
                    $payload[$key] = true;
                    continue;
                }
                if (in_array($key, ['title_tl', 'duration_seconds', 'sort_order'], true)) {
                    continue;
                }
            }

            if (! array_key_exists($key, $data) && $partial) {
                continue;
            }

            $value = $data[$key] ?? null;

            if ($key === 'title') {
                $payload[$key] = filled($value) ? trim((string) $value) : 'Relaxing track';
            } elseif ($key === 'title_tl') {
                $payload[$key] = filled($value) ? trim((string) $value) : null;
            } elseif ($key === 'is_active') {
                $payload[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            } elseif ($key === 'duration_seconds') {
                $payload[$key] = ($value === null || $value === '') ? null : (int) $value;
            } elseif ($key === 'sort_order' && $value !== null && $value !== '') {
                $payload[$key] = (int) $value;
            }
        }

        return $payload;
    }

    private function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete(ltrim($path, '/'));
    }
}
