<?php

namespace App\Services;

use App\Models\SongContest;
use App\Models\SongContestEntry;
use App\Models\User;
use App\Repositories\SongContestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SongContestService
{
    public function __construct(
        private readonly SongContestRepository $songContestRepository,
    ) {
    }

    public function listContests(
        int $perPage = 20,
        ?string $search = null,
        ?string $status = null,
        bool $activeOnly = true,
        bool $publicOnly = false,
    ): LengthAwarePaginator {
        return $this->songContestRepository->paginateContests(
            $perPage,
            $search,
            $status,
            $activeOnly,
            $publicOnly,
        );
    }

    public function findContest(int $id): SongContest
    {
        return $this->songContestRepository->findContestOrFail($id);
    }

    public function createContest(array $data): SongContest
    {
        return $this->songContestRepository->createContest($data);
    }

    public function updateContest(SongContest $contest, array $data): SongContest
    {
        return $this->songContestRepository->updateContest($contest, $data);
    }

    public function deleteContest(SongContest $contest): void
    {
        $this->songContestRepository->deleteContest($contest);
    }

    public function contestDetailForPublic(SongContest $contest): array
    {
        $contest->loadCount([
            'entries',
            'entries as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ]);

        return [
            'contest' => $contest,
            'entries' => $this->songContestRepository->publishedEntries($contest),
        ];
    }

    public function listEntries(
        SongContest $contest,
        int $perPage = 50,
        ?string $status = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->songContestRepository->paginateEntries(
            $contest,
            $perPage,
            $status,
            $search,
        );
    }

    public function findEntry(int $id): SongContestEntry
    {
        return $this->songContestRepository->findEntryOrFail($id);
    }

    public function submitEntry(SongContest $contest, User $user, array $data): SongContestEntry
    {
        if (! $contest->is_open_for_submission) {
            throw ValidationException::withMessages([
                'contest' => ['This contest is not open for submissions.'],
            ]);
        }

        $entryType = $data['entry_type'] ?? 'song';
        if (! $contest->allowsEntryType($entryType)) {
            throw ValidationException::withMessages([
                'entry_type' => ['This contest does not accept that entry type.'],
            ]);
        }

        if ($this->songContestRepository->findUserEntry($contest, $user->id)) {
            throw ValidationException::withMessages([
                'contest' => ['You already submitted an entry for this contest.'],
            ]);
        }

        return $this->songContestRepository->createEntry([
            ...$data,
            'song_contest_id' => $contest->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function myEntry(SongContest $contest, User $user): ?SongContestEntry
    {
        return $this->songContestRepository->findUserEntry($contest, $user->id);
    }

    public function reviewEntry(
        SongContestEntry $entry,
        User $admin,
        string $status,
        ?string $adminNotes = null,
    ): SongContestEntry {
        if (! in_array($status, ['approved', 'rejected', 'finalist'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid review status.'],
            ]);
        }

        return $this->songContestRepository->updateEntry($entry, [
            'status' => $status,
            'admin_notes' => $adminNotes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function setWinner(SongContestEntry $entry, User $admin): SongContestEntry
    {
        return DB::transaction(function () use ($entry, $admin) {
            $contest = $entry->contest;

            $this->songContestRepository->clearWinners($contest);

            $updated = $this->songContestRepository->updateEntry($entry, [
                'status' => 'winner',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if (in_array($contest->status, ['open', 'closed'], true)) {
                $this->songContestRepository->updateContest($contest, [
                    'status' => 'completed',
                ]);
            }

            return $updated;
        });
    }
}
