<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class AccessTokenService
{
    public const PENDING_ABILITY = 'login-pending';

    public function issuePending(User $user): string
    {
        return $user->createToken(
            'login-pending',
            [self::PENDING_ABILITY],
            now()->addMinutes(10),
        )->plainTextToken;
    }

    public function issueFull(User $user, string $name = 'api-token'): string
    {
        return $user->createToken(
            $name,
            ['*'],
            now()->addDays(30),
        )->plainTextToken;
    }

    public function revokeCurrent(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }

    public function revokeOthers(User $user): void
    {
        $currentId = $user->currentAccessToken()?->id;
        $query = $user->tokens();

        if ($currentId) {
            $query->where('id', '!=', $currentId);
        }

        $query->delete();
    }

    public function isPending(User $user): bool
    {
        $token = $user->currentAccessToken();
        if ($token === null) {
            return false;
        }

        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return $token->can(self::PENDING_ABILITY) && ! $token->can('*');
    }
}
