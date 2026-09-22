<?php

namespace App\Services;

use App\Models\MoodCheckin;
use App\Models\User;
use App\Repositories\MoodCheckinRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MoodCheckinService
{
    public function __construct(
        private readonly MoodCheckinRepository $moodCheckinRepository,
    ) {
    }

    public function list(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->moodCheckinRepository->paginateForUser($user, $perPage);
    }

    public function today(User $user): ?MoodCheckin
    {
        return $this->moodCheckinRepository->latestTodayForUser($user);
    }

    public function store(User $user, array $data): MoodCheckin
    {
        $note = isset($data['note']) ? trim((string) $data['note']) : null;

        return $this->moodCheckinRepository->create($user, [
            'mood'   => $data['mood'],
            'source' => $data['source'] ?? 'care_hub',
            'score'  => $data['score'] ?? null,
            'note'   => ($note === null || $note === '') ? null : $note,
        ]);
    }

    public function format(MoodCheckin $checkin): array
    {
        return [
            'id'         => $checkin->id,
            'mood'       => $checkin->mood,
            'source'     => $checkin->source,
            'score'      => $checkin->score,
            'note'       => $checkin->note,
            'created_at' => $checkin->created_at?->toIso8601String(),
            'updated_at' => $checkin->updated_at?->toIso8601String(),
        ];
    }
}
