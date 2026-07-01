<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileService $profileService,
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
        return $this->userRepository->create($data);
    }

    public function updateAdminUser(User $user, array $data): User
    {
        return $this->userRepository->update($user, $data);
    }

    public function delete(User $user): void
    {
        $this->userRepository->delete($user);
    }
}

