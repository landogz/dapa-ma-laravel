<?php

namespace App\Support\Username;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class Username
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 30;

    /**
     * Public username pattern: letters, numbers, underscore, dot, hyphen.
     */
    public const PATTERN = '/^[A-Za-z0-9._-]+$/';

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $username = trim($value);
        $username = ltrim($username, '@');
        $username = trim($username);

        return $username === '' ? null : $username;
    }

    /**
     * @return list<string|Unique>
     */
    public static function rules(?int $ignoreUserId = null, bool $required = false): array
    {
        $unique = Rule::unique('users', 'nickname');
        if ($ignoreUserId !== null) {
            $unique = $unique->ignore($ignoreUserId);
        }

        return [
            $required ? 'required' : 'sometimes',
            'nullable',
            'string',
            'min:'.self::MIN_LENGTH,
            'max:'.self::MAX_LENGTH,
            'regex:'.self::PATTERN,
            $unique,
            // Case-insensitive check (collation-safe companion to Rule::unique).
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                if (self::isTaken($value, $ignoreUserId)) {
                    $fail('This username is already taken.');
                }
            },
        ];
    }

    public static function isTaken(string $username, ?int $ignoreUserId = null): bool
    {
        $normalized = self::normalize($username);
        if ($normalized === null) {
            return false;
        }

        $query = User::query()->whereRaw('LOWER(nickname) = ?', [mb_strtolower($normalized)]);
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }
}
