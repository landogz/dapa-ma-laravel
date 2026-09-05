<?php

namespace App\Services;

use App\Models\VideoContest;
use App\Models\VideoContestEntry;
use App\Models\User;
use App\Repositories\VideoContestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VideoContestService
{
    public function __construct(
        private readonly VideoContestRepository $videoContestRepository,
    ) {
    }

    public function listContests(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        bool $activeOnly = true,
        bool $publicOnly = false,
    ): LengthAwarePaginator {
        return $this->videoContestRepository->paginateContests(
            $perPage,
            $search,
            $status,
            $activeOnly,
            $publicOnly,
        );
    }

    public function findContest(int $id): VideoContest
    {
        return $this->videoContestRepository->findContestOrFail($id);
    }

    public function createContest(array $data): VideoContest
    {
        return $this->videoContestRepository->createContest($data);
    }

    public function updateContest(VideoContest $contest, array $data): VideoContest
    {
        return $this->videoContestRepository->updateContest($contest, $data);
    }

    public function deleteContest(VideoContest $contest): void
    {
        $this->videoContestRepository->deleteContest($contest);
    }

    public function contestDetailForPublic(VideoContest $contest): array
    {
        $contest->loadCount([
            'entries',
            'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);

        return [
            'contest' => $contest,
            'entries' => $this->videoContestRepository->publishedEntries($contest),
        ];
    }

    public function listEntries(
        VideoContest $contest,
        int $perPage = 50,
        ?string $status = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->videoContestRepository->paginateEntries(
            $contest,
            $perPage,
            $status,
            $search,
        );
    }

    public function submitEntry(
        VideoContest $contest,
        User $user,
        array $data,
    ): VideoContestEntry {
        if (! $contest->is_open_for_submission) {
            throw ValidationException::withMessages([
                'contest' => ['This contest is not open for submissions.'],
            ]);
        }

        if ($this->videoContestRepository->findUserEntry($contest, $user->id)) {
            throw ValidationException::withMessages([
                'contest' => ['You already submitted an entry for this contest.'],
            ]);
        }

        $videoUrl = $data['video_url'] ?? null;
        if (! $videoUrl) {
            throw ValidationException::withMessages([
                'video_url' => ['A YouTube or video URL is required.'],
            ]);
        }

        return $this->videoContestRepository->createEntry([
            'video_contest_id' => $contest->id,
            'user_id' => $user->id,
            'title' => $data['title'],
            'creator_name' => $data['creator_name'],
            'description' => $data['description'] ?? null,
            'video_url' => $videoUrl,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'region' => $data['region'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function myEntry(VideoContest $contest, User $user): ?VideoContestEntry
    {
        return $this->videoContestRepository->findUserEntry($contest, $user->id);
    }

    public function reviewEntry(
        VideoContestEntry $entry,
        User $admin,
        string $status,
        ?string $adminNotes = null,
    ): VideoContestEntry {
        if (! in_array($status, ['approved', 'rejected', 'finalist'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid review status.'],
            ]);
        }

        return $this->videoContestRepository->updateEntry($entry, [
            'status' => $status,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function setWinner(VideoContestEntry $entry, User $admin): VideoContestEntry
    {
        return DB::transaction(function () use ($entry, $admin) {
            $contest = $entry->contest;

            $this->videoContestRepository->clearWinners($contest);

            $updated = $this->videoContestRepository->updateEntry($entry, [
                'status' => 'winner',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if (in_array($contest->status, ['open', 'closed'], true)) {
                $this->videoContestRepository->updateContest($contest, [
                    'status' => 'completed',
                ]);
            }

            return $updated;
        });
    }
}
