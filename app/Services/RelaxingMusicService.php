<?php

namespace App\Services;

use App\Models\RelaxingMusicTrack;
use App\Models\User;
use App\Repositories\RelaxingMusicTrackRepository;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class RelaxingMusicService
{
    private const SECTION = 'relaxing-music';

    public function __construct(
        private readonly RelaxingMusicTrackRepository $repository,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    /**
     * @param  array{search?: ?string, is_active?: bool|null, workflow_status?: ?string}  $filters
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function listAdmin(array $filters = [], ?User $actor = null): Collection
    {
        return $this->repository->listAdmin($filters, $actor);
    }

    /**
     * @return Collection<int, RelaxingMusicTrack>
     */
    public function publicList(): Collection
    {
        return $this->repository->listPublishedActive();
    }

    public function find(int $id): RelaxingMusicTrack
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(?User $actor = null): array
    {
        return $this->repository->statusCounts($actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): RelaxingMusicTrack
    {
        $payload = $this->normalize($data);

        if (! (($data['audio'] ?? null) instanceof UploadedFile)) {
            throw new InvalidArgumentException('Audio file is required.');
        }

        $payload['audio_path'] = $data['audio']->store('relaxing-music', 'public');

        if (! array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = $this->repository->nextSortOrder();
        }

        $payload = $this->contentWorkflow->prepareCreatePayload($actor, $payload);

        $track = $this->repository->create($payload);

        // Editors skip Draft — new uploads go straight to the Publisher Pending queue.
        if (
            $actor->role === 'editor'
            && ($track->workflow_status ?? null) === ContentWorkflowStatus::DRAFT
        ) {
            return $this->contentWorkflow->submit($track, $actor, self::SECTION);
        }

        return $track;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<RelaxingMusicTrack>
     */
    public function createMany(array $data, User $actor): array
    {
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            $data['audios'] ?? (($data['audio'] ?? null) instanceof UploadedFile ? [$data['audio']] : []),
            static fn ($file) => $file instanceof UploadedFile,
        ));

        if ($files === []) {
            throw new InvalidArgumentException('At least one audio file is required.');
        }

        $nextSort = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== ''
            ? (int) $data['sort_order']
            : $this->repository->nextSortOrder();

        $created = [];

        foreach ($files as $index => $file) {
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
            ], $actor);
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RelaxingMusicTrack $track, array $data, User $actor): RelaxingMusicTrack
    {
        $payload = $this->normalize($data, partial: true);
        $previousAudio = $track->audio_path;
        $replacingAudio = ($data['audio'] ?? null) instanceof UploadedFile;

        if ($replacingAudio) {
            $payload['audio_path'] = $data['audio']->store('relaxing-music', 'public');
        }

        $payload = $this->contentWorkflow->prepareUpdatePayload(
            $track,
            $actor,
            $payload,
            self::SECTION,
        );

        // Only delete the live file when the update applied audio_path on the row itself
        // (super admin / draft edits). Staged revisions keep the live file until publish.
        if (
            $replacingAudio
            && $previousAudio
            && array_key_exists('audio_path', $payload)
            && $payload['audio_path'] !== $previousAudio
        ) {
            $this->deleteFile($previousAudio);
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
