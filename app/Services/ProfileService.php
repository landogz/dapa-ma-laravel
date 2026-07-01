<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    public function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'profile_image_url' => $this->profileImageUrl($user),
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

        if (isset($payload['first_name']) || isset($payload['last_name'])) {
            $firstName = $payload['first_name'] ?? $user->first_name ?? '';
            $lastName = $payload['last_name'] ?? $user->last_name ?? '';
            $payload['name'] = trim($firstName.' '.$lastName);
        }

        if (($data['profile_photo'] ?? null) instanceof UploadedFile) {
            $this->deleteStoredImage($user->profile_image_url);
            $payload['profile_image_url'] = $data['profile_photo']->store('profiles', 'public');
        } elseif (! empty($data['remove_profile_photo'])) {
            $this->deleteStoredImage($user->profile_image_url);
            $payload['profile_image_url'] = null;
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

    private function deleteStoredImage(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
