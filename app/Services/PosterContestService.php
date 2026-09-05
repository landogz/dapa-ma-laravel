<?php

namespace App\Services;

use App\Models\PosterContest;
use App\Models\PosterContestEntry;
use App\Models\User;
use App\Repositories\PosterContestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosterContestService
{
    public function __construct(
        private readonly PosterContestRepository $posterContestRepository,
    ) {
    }

    public function listContests(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        bool $activeOnly = true,
        bool $publicOnly = false,
    ): LengthAwarePaginator {
        return $this->posterContestRepository->paginateContests(
            $perPage,
            $search,
            $status,
            $activeOnly,
            $publicOnly,
        );
    }

    public function findContest(int $id): PosterContest
    {
        return $this->posterContestRepository->findContestOrFail($id);
    }

    public function createContest(array $data): PosterContest
    {
        return $this->posterContestRepository->createContest($data);
    }

    public function updateContest(PosterContest $contest, array $data): PosterContest
    {
        return $this->posterContestRepository->updateContest($contest, $data);
    }

    public function deleteContest(PosterContest $contest): void
    {
        $this->posterContestRepository->deleteContest($contest);
    }

    public function contestDetailForPublic(PosterContest $contest): array
    {
        $contest->loadCount([
            'entries',
            'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);

        return [
            'contest' => $contest,
            'entries' => $this->posterContestRepository->publishedEntries($contest),
        ];
    }

    public function listEntries(
        PosterContest $contest,
        int $perPage = 50,
        ?string $status = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->posterContestRepository->paginateEntries(
            $contest,
            $perPage,
            $status,
            $search,
        );
    }

    public function submitEntry(
        PosterContest $contest,
        User $user,
        array $data,
        ?UploadedFile $posterImage = null,
    ): PosterContestEntry {
        if (! $contest->is_open_for_submission) {
            throw ValidationException::withMessages([
                'contest' => ['This contest is not open for submissions.'],
            ]);
        }

        if ($this->posterContestRepository->findUserEntry($contest, $user->id)) {
            throw ValidationException::withMessages([
                'contest' => ['You already submitted an entry for this contest.'],
            ]);
        }

        $imageUrl = $data['poster_image_url'] ?? null;

        if ($posterImage) {
            $imageUrl = $posterImage->store('poster-contest-entries', 'public');
        }

        if (! $imageUrl) {
            throw ValidationException::withMessages([
                'poster_image' => ['A poster image or image URL is required.'],
            ]);
        }

        return $this->posterContestRepository->createEntry([
            'poster_contest_id' => $contest->id,
            'user_id' => $user->id,
            'title' => $data['title'],
            'creator_name' => $data['creator_name'],
            'description' => $data['description'] ?? null,
            'poster_image_url' => $imageUrl,
            'region' => $data['region'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function myEntry(PosterContest $contest, User $user): ?PosterContestEntry
    {
        return $this->posterContestRepository->findUserEntry($contest, $user->id);
    }

    public function reviewEntry(
        PosterContestEntry $entry,
        User $admin,
        string $status,
        ?string $adminNotes = null,
    ): PosterContestEntry {
        if (! in_array($status, ['approved', 'rejected', 'finalist'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid review status.'],
            ]);
        }

        return $this->posterContestRepository->updateEntry($entry, [
            'status' => $status,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function setWinner(PosterContestEntry $entry, User $admin): PosterContestEntry
    {
        return DB::transaction(function () use ($entry, $admin) {
            $contest = $entry->contest;

            $this->posterContestRepository->clearWinners($contest);

            $updated = $this->posterContestRepository->updateEntry($entry, [
                'status' => 'winner',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if (in_array($contest->status, ['open', 'closed'], true)) {
                $this->posterContestRepository->updateContest($contest, [
                    'status' => 'completed',
                ]);
            }

            return $updated;
        });
    }
}
