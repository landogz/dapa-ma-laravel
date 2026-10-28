<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Password\PasswordService;
use App\Support\Password\PasswordPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileService $profileService,
        private readonly PasswordService $passwordService,
    ) {
    }

    public function listPaginated(int $perPage = 15): LengthAwarePaginator
    {
        $paginator = $this->userRepository->paginate($perPage);

        $paginator->getCollection()->transform(function (User $user): User {
            $user->setAttribute(
                'profile_image_url',
                $this->profileService->profileImageUrl($user),
            );

            return $user;
        });

        return $paginator;
    }

    public function createAdminUser(array $data): User
    {
        $plainPassword = (string) ($data['password'] ?? '');
        unset($data['password_confirmation']);

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
}

