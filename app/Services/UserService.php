<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Password\PasswordService;
use App\Support\Password\PasswordPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileService $profileService,
        private readonly PasswordService $passwordService,
    ) {
    }

    /**
     * @return array{paginator: LengthAwarePaginator, summary: array<string, mixed>}
     */
    public function listPaginated(int $perPage = 15, array $filters = []): array
    {
        $paginator = $this->userRepository->paginate($perPage, $filters);

        $paginator->getCollection()->transform(function (User $user): User {
            $user->setAttribute(
                'profile_image_url',
                $this->profileService->profileImageUrl($user),
            );

            return $user;
        });

        return [
            'paginator' => $paginator,
            'summary' => $this->userRepository->summary($filters),
        ];
    }

    public function createAdminUser(array $data): User
    {
        $plainPassword = (string) ($data['password'] ?? '');
        unset($data['password_confirmation']);

        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        $user = $this->userRepository->create($data);

        if ($plainPassword !== '') {
            $this->passwordService->apply(
                $user,
                $plainPassword,
                PasswordPolicy::forceChangeOnAdminSet(),
            );
        }

        return $user;
    }

    public function updateAdminUser(User $user, array $data): User
    {
        $plainPassword = (string) ($data['password'] ?? '');
        unset($data['password'], $data['password_confirmation']);

        $updated = $data === []
            ? $user
            : $this->userRepository->update($user, $data);

        if ($plainPassword !== '') {
            $this->passwordService->apply(
                $updated,
                $plainPassword,
                PasswordPolicy::forceChangeOnAdminSet(),
            );
        }

        return $updated->refresh();
    }

    public function delete(User $user): void
    {
        $this->userRepository->delete($user);
    }

    public function forcePasswordChange(User $actor, User $user): User
    {
        if ((int) $actor->getKey() === (int) $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot force a password change on your own account.'],
            ]);
        }

        $user->forceFill(['must_change_password' => true])->save();

        return $user->refresh();
    }

    public function suspend(User $actor, User $user): User
    {
        if ((int) $actor->getKey() === (int) $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot suspend your own account.'],
            ]);
        }

        if ($this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => ['The last active super admin account cannot be suspended.'],
            ]);
        }

        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();

        return $user->refresh();
    }

    public function activate(User $user): User
    {
        $user->forceFill(['is_active' => true])->save();

        return $user->refresh();
    }

    public function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->role === 'super_admin'
            && $user->is_active !== false
            && $this->userRepository->countActiveSuperAdmins() <= 1;
    }
}
