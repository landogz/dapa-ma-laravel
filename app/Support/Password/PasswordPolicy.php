<?php

namespace App\Support\Password;

use App\Models\User;
use App\Rules\Password\NotRecentlyUsed;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class PasswordPolicy
{
    public static function hint(): string
    {
        return (string) config('password.hint', 'Password must include at least 8 characters, upper and lower case, a number, a symbol.');
    }

    public static function minLength(): int
    {
        return max(8, (int) config('password.min_length', 8));
    }

    public static function expireDays(): int
    {
        return max(0, (int) config('password.expire_days', 90));
    }

    public static function historyCount(): int
    {
        return max(0, (int) config('password.history_count', 5));
    }

    public static function forceChangeOnAdminSet(): bool
    {
        return (bool) config('password.force_change_on_admin_set', true);
    }

    public static function rule(): PasswordRule
    {
        $rule = PasswordRule::min(self::minLength());

        if (config('password.require_mixed_case', true)) {
            $rule->mixedCase();
        }

        if (config('password.require_number', true)) {
            $rule->numbers();
        }

        if (config('password.require_symbol', true)) {
            $rule->symbols();
        }

        if (config('password.uncompromised', true)) {
            $rule->uncompromised((int) config('password.uncompromised_threshold', 0));
        }

        return $rule;
    }

    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true, bool $confirmed = true, ?User $user = null): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'string',
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        $rules[] = self::rule();

        if ($user instanceof User) {
            $rules[] = new NotRecentlyUsed($user);
        }

        return $rules;
    }

    public static function meetsComplexity(string $value): bool
    {
        return self::complexityError($value) === null;
    }

    public static function complexityError(?string $value): ?string
    {
        $password = (string) $value;

        if ($password === '') {
            return null;
        }

        $min = self::minLength();

        if (mb_strlen($password) < $min) {
            return self::hint();
        }

        if (config('password.require_mixed_case', true)
            && (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password))) {
            return self::hint();
        }

        if (config('password.require_number', true) && ! preg_match('/\d/', $password)) {
            return self::hint();
        }

        if (config('password.require_symbol', true) && ! preg_match('/[\p{P}\p{S}]/u', $password)) {
            return self::hint();
        }

        return null;
    }
}
