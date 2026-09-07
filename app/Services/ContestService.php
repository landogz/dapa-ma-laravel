<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use App\Repositories\ContestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContestService
{
    public function __construct(
        private readonly ContestRepository $contestRepository,
    ) {
    }

    public function listContests(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        ?string $category = null,
        bool $activeOnly = true,
        bool $publicOnly = false,
    ): LengthAwarePaginator {
        return $this->contestRepository->paginateContests(
            $perPage,
            $search,
            $status,
            $category,
            $activeOnly,
            $publicOnly,
        );
    }

    public function findContest(int $id): Contest
    {
        return $this->contestRepository->findContestOrFail($id);
    }

    public function createContest(array $data): Contest
    {
        return $this->contestRepository->createContest($data);
    }

    public function updateContest(Contest $contest, array $data): Contest
    {
        return $this->contestRepository->updateContest($contest, $data);
    }

    public function deleteContest(Contest $contest): void
    {
        $this->contestRepository->deleteContest($contest);
    }

    public function contestDetailForPublic(Contest $contest): array
    {
        $contest->loadCount([
            'entries',
            'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);

        return [
            'contest' => $contest,
            'entries' => $this->contestRepository->publishedEntries($contest),
        ];
    }

    public function listEntries(
        Contest $contest,
        int $perPage = 50,
        ?string $status = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->contestRepository->paginateEntries(
            $contest,
            $perPage,
            $status,
            $search,
        );
    }

    public function findEntry(int $id): ContestEntry
    {
        return $this->contestRepository->findEntryOrFail($id);
    }

    public function submitEntry(
        Contest $contest,
        User $user,
        array $data,
        ?UploadedFile $posterImage = null,
    ): ContestEntry {
        if (! $contest->is_open_for_submission) {
            throw ValidationException::withMessages([
                'contest' => ['This contest is not open for submissions.'],
            ]);
        }

        if ($this->contestRepository->findUserEntry($contest, $user->id)) {
            throw ValidationException::withMessages([
                'contest' => ['You already submitted an entry for this contest.'],
            ]);
        }

        $payload = match ($contest->category) {
            'song' => $this->buildSongEntryPayload($contest, $data),
            'poster' => $this->buildPosterEntryPayload($data, $posterImage),
            'video' => $this->buildVideoEntryPayload($data),
            default => throw ValidationException::withMessages([
                'contest' => ['Unsupported contest category.'],
            ]),
        };

        return $this->contestRepository->createEntry([
            ...$payload,
            'contest_id' => $contest->id,
            'user_id' => $user->id,
            'title' => $data['title'],
            'creator_name' => $data['creator_name'],
            'description' => $data['description'] ?? null,
            'region' => $data['region'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function myEntry(Contest $contest, User $user): ?ContestEntry
    {
        return $this->contestRepository->findUserEntry($contest, $user->id);
    }

    public function reviewEntry(
        ContestEntry $entry,
        User $admin,
        string $status,
        ?string $adminNotes = null,
    ): ContestEntry {
        if (! in_array($status, ['approved', 'rejected', 'finalist'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid review status.'],
            ]);
        }

        return $this->contestRepository->updateEntry($entry, [
            'status' => $status,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function setWinner(ContestEntry $entry, User $admin): ContestEntry
    {
        return DB::transaction(function () use ($entry, $admin) {
            $contest = $entry->contest;

            $this->contestRepository->clearWinners($contest);

            $updated = $this->contestRepository->updateEntry($entry, [
                'status' => 'winner',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if (in_array($contest->status, ['open', 'closed'], true)) {
                $this->contestRepository->updateContest($contest, [
                    'status' => 'completed',
                ]);
            }

            return $updated;
        });
    }

    private function buildSongEntryPayload(Contest $contest, array $data): array
    {
        $entryType = $data['entry_type'] ?? 'song';

        if (! $contest->allowsEntryType($entryType)) {
            throw ValidationException::withMessages([
                'entry_type' => ['This contest does not accept that entry type.'],
            ]);
        }

        $mediaUrl = $data['media_url'] ?? null;
        $lyrics = $data['lyrics'] ?? null;

        if (! $mediaUrl && ! $lyrics) {
            throw ValidationException::withMessages([
                'media_url' => ['A media URL or lyrics is required for song entries.'],
            ]);
        }

        return [
            'entry_type' => $entryType,
            'lyrics' => $lyrics,
            'media_url' => $mediaUrl,
            'cover_image_url' => $data['cover_image_url'] ?? null,
        ];
    }

    private function buildPosterEntryPayload(array $data, ?UploadedFile $posterImage = null): array
    {
        $imageUrl = $data['poster_image_url'] ?? $data['media_url'] ?? null;

        if ($posterImage) {
            $imageUrl = $posterImage->store('contest-entries/posters', 'public');
        }

        if (! $imageUrl) {
            throw ValidationException::withMessages([
                'poster_image' => ['A poster image or image URL is required.'],
            ]);
        }

        return [
            'poster_image_url' => $imageUrl,
            'media_url' => $imageUrl,
            'cover_image_url' => $imageUrl,
        ];
    }

    private function buildVideoEntryPayload(array $data): array
    {
        $videoUrl = $data['video_url'] ?? null;

        if (! $videoUrl) {
            throw ValidationException::withMessages([
                'video_url' => ['A YouTube or video URL is required.'],
            ]);
        }

        $thumbnailUrl = $data['thumbnail_url'] ?? null;

        return [
            'video_url' => $videoUrl,
            'thumbnail_url' => $thumbnailUrl,
            'media_url' => $videoUrl,
            'cover_image_url' => $thumbnailUrl,
        ];
    }
}
