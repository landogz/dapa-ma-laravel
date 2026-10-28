<?php

namespace App\Services;

use App\Models\User;
use App\Services\Password\PasswordService;
use App\Support\InterestValues;
use App\Support\Journal\CustomFeelings;
use App\Support\Username\Username;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {
    }

    public function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'nickname' => $user->nickname,
            'pronouns' => $user->pronouns,
            'birthday' => $user->birthday?->toDateString(),
            'persona' => $user->persona,
            'personas' => $this->personasOf($user),
            'interests' => $user->interests ?? [],
            'onboarding_completed_at' => $user->onboarding_completed_at?->toIso8601String(),
            'email' => $user->email,
            'role' => $user->role,
            'profile_image_url' => $this->profileImageUrl($user),
            'about' => $user->about,
            'journal_custom_feelings' => CustomFeelings::forUser($user),
            'must_change_password' => $this->passwordService->requiresChange($user),
            'password_expired' => $this->passwordService->isExpired($user),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    public function update(User $user, array $data): User
    {
        $payload = [];

        if (array_key_exists('first_name', $data)) {
            $payload['first_name'] = trim($data['first_name']);
        }

        if (array_key_exists('last_name', $data)) {
            $payload['last_name'] = trim($data['last_name']);
        }

        // Mobile may send a single "name" field instead of first/last.
        if (! isset($payload['first_name']) && ! isset($payload['last_name'])
            && array_key_exists('name', $data) && is_string($data['name'])) {
            $fullName = trim($data['name']);
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $payload['first_name'] = $parts[0] ?? $fullName;
            $payload['last_name'] = $parts[1] ?? '';
            $payload['name'] = $fullName;
        }

        if (isset($payload['first_name']) || isset($payload['last_name'])) {
            $firstName = $payload['first_name'] ?? $user->first_name ?? '';
            $lastName = $payload['last_name'] ?? $user->last_name ?? '';
            $payload['name'] = trim($firstName.' '.$lastName);
        }

        // Username / nickname is independent of full name.
        if (array_key_exists('nickname', $data)) {
            $payload['nickname'] = Username::normalize($data['nickname'] ?? null);
        }

        if (($data['profile_photo'] ?? null) instanceof UploadedFile) {
            $this->deleteStoredImage($user->profile_image_url);
            $payload['profile_image_url'] = $data['profile_photo']->store('profiles', 'public');
        } elseif (! empty($data['remove_profile_photo'])) {
            $this->deleteStoredImage($user->profile_image_url);
            $payload['profile_image_url'] = null;
        }

        if (array_key_exists('about', $data)) {
            $about = is_string($data['about']) ? trim($data['about']) : '';
            $payload['about'] = $about === '' ? null : $about;
        }

        if (array_key_exists('journal_custom_feelings', $data)) {
            $feelings = $data['journal_custom_feelings'];
            $payload['journal_custom_feelings'] = is_array($feelings)
                ? CustomFeelings::customFrom($feelings)
                : null;
        }

        if (array_key_exists('interests', $data)) {
            $interests = $data['interests'];
            if (! is_array($interests)) {
                $payload['interests'] = null;
            } else {
                $normalized = InterestValues::normalize($interests);
                $payload['interests'] = $normalized === [] ? null : $normalized;
            }
        }

        if ($payload !== []) {
            $user->update($payload);
        }

        return $user->fresh();
    }

    public function updateOnboarding(User $user, array $data): User
    {
        $payload = [];

        // Nickname is the public username. Keep it separate from full name.
        if (array_key_exists('nickname', $data)) {
            $payload['nickname'] = Username::normalize($data['nickname'] ?? null);
        }

        if (array_key_exists('pronouns', $data)) {
            $pronouns = $data['pronouns'];
            $payload['pronouns'] = ($pronouns === null || $pronouns === '') ? null : $pronouns;
        }

        if (array_key_exists('birthday', $data)) {
            $payload['birthday'] = $data['birthday'] ?: null;
        }

        if (array_key_exists('personas', $data) || array_key_exists('persona', $data)) {
            $personas = $this->normalizePersonas($data);
            $payload['personas'] = $personas;
            $payload['persona'] = $personas[0] ?? null;
        }

        if (array_key_exists('interests', $data)) {
            $interests = $data['interests'];
            if (! is_array($interests)) {
                $payload['interests'] = null;
            } else {
                $normalized = InterestValues::normalize($interests);
                $payload['interests'] = $normalized === [] ? null : $normalized;
            }
        }

        if (! empty($data['complete'])) {
            $payload['onboarding_completed_at'] = now();
        }

        if ($payload !== []) {
            $user->update($payload);
        }

        return $user->fresh();
    }

    public function profileImageUrl(User $user): ?string
    {
        if (! $user->profile_image_url) {
            return null;
        }

        if (Str::startsWith($user->profile_image_url, ['http://', 'https://'])) {
            return $user->profile_image_url;
        }

        return Storage::disk('public')->url($user->profile_image_url);
    }

    /**
     * @return list<string>
     */
    private function personasOf(User $user): array
    {
        if (is_array($user->personas) && $user->personas !== []) {
            return array_values(array_filter(array_map('strval', $user->personas)));
        }

        return $user->persona ? [$user->persona] : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>|null
     */
    private function normalizePersonas(array $data): ?array
    {
        if (array_key_exists('personas', $data)) {
            $raw = $data['personas'];
            if (! is_array($raw)) {
                return null;
            }

            $list = [];
            foreach ($raw as $value) {
                $key = trim((string) $value);
                if ($key !== '' && ! in_array($key, $list, true)) {
                    $list[] = $key;
                }
            }

            return $list === [] ? null : $list;
        }

        $persona = $data['persona'] ?? null;
        if ($persona === null || $persona === '') {
            return null;
        }

        return [trim((string) $persona)];
    }

    private function deleteStoredImage(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
