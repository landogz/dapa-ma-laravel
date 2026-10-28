<?php

namespace App\Rules\Password;

use App\Models\Password\PasswordHistory;
use App\Models\User;
use App\Support\Password\PasswordPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class NotRecentlyUsed implements ValidationRule
{
    public function __construct(private readonly User $user)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $plain = is_string($value) ? $value : '';

        if ($plain === '') {
            return;
        }

        if (is_string($this->user->password) && $this->user->password !== '' && Hash::check($plain, $this->user->password)) {
            $fail('New password must be different from your current password.');

            return;
        }

        $limit = PasswordPolicy::historyCount();

        if ($limit < 1) {
            return;
        }

        $hashes = PasswordHistory::query()
            ->where('user_id', $this->user->getKey())
            ->latest('id')
            ->limit($limit)
            ->pluck('password');

        foreach ($hashes as $hash) {
            if (is_string($hash) && $hash !== '' && Hash::check($plain, $hash)) {
                $fail('This password was used recently. Choose a different password.');

                return;
            }
        }
    }
}
