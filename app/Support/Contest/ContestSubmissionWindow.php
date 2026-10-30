<?php

namespace App\Support\Contest;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

final class ContestSubmissionWindow
{
    /**
     * Status "open" alone is not enough — mobile submit also requires today (Asia/Manila)
     * to fall inside the submission start/end dates when those dates are set.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validateOpenStatusDates(Validator $validator, array $data): void
    {
        if (($data['status'] ?? null) !== 'open') {
            return;
        }

        $today = Carbon::now('Asia/Manila')->toDateString();
        $end = isset($data['submission_ends_at']) && $data['submission_ends_at'] !== ''
            ? Carbon::parse((string) $data['submission_ends_at'], 'Asia/Manila')->toDateString()
            : null;

        if ($end !== null && $end < $today) {
            $validator->errors()->add(
                'submission_ends_at',
                'Submission end date is already past (Asia/Manila). Extend it so the mobile app can accept entries while status is Open.',
            );
        }
    }
}
