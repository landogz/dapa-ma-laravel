<?php

namespace App\Services\Password;

use App\Models\User;
use App\Repositories\Password\PasswordHistoryRepository;
use App\Support\Password\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    public function __construct(
        private readonly PasswordHistoryRepository $passwordHistoryRepository,
    ) {
    }

    public function apply(User $user, string $plainPassword, bool $forceChange = false): User
    {
        $currentHash = $this->currentHash($user);
        $alreadyCurrent = $currentHash !== '' && Hash::check($plainPassword, $currentHash);

        if (! $alreadyCurrent) {
            $this->assertNotReused($user, $plainPassword);

            if ($currentHash !== '') {
                $this->passwordHistoryRepository->record($user, $currentHash);
                $this->passwordHistoryRepository->prune($user);
            }

            $user->password = $plainPassword;
        }

        $user->forceFill([
            'password_changed_at' => now(),
            'must_change_password' => $forceChange,
        ])->save();

        return $user->refresh();
    }

    public function isExpired(User $user): bool
    {
        $days = PasswordPolicy::expireDays();

        if ($days < 1 || $user->password_changed_at === null) {
            return false;
        }

        return $user->password_changed_at->copy()->addDays($days)->isPast();
    }

    public function requiresChange(User $user): bool
    {
        return (bool) $user->must_change_password || $this->isExpired($user);
    }

    public function assertNotReused(User $user, string $plainPassword): void
    {
        $currentHash = $this->currentHash($user);

        if ($currentHash !== '' && Hash::check($plainPassword, $currentHash)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from your current password.'],
            ]);
        }

        foreach ($this->passwordHistoryRepository->recentHashes($user) as $hash) {
            if (is_string($hash) && $hash !== '' && Hash::check($plainPassword, $hash)) {
                throw ValidationException::withMessages([
                    'password' => ['This password was used recently. Choose a different password.'],
                ]);
            }
        }
    }

    private function currentHash(User $user): string
    {
        $raw = $user->getRawOriginal('password');

        return is_string($raw) ? $raw : '';
    }
}
