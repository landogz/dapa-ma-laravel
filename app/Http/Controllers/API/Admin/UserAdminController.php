<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserAdminController extends Controller
{
    public function __construct(
        private readonly UserService              $userService,
        private readonly AdminNotificationService $adminNotificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);

        return response()->json([
            'status'  => true,
            'message' => 'Users fetched.',
            'data'    => $this->userService->listPaginated($perPage),
        ]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $user = $this->userService->createAdminUser($request->validated());

        $actor = $request->user();

        if ($actor instanceof User) {
            $this->adminNotificationService->notifyUserCreated($user, $actor);
        }

        return response()->json([
            'status'  => true,
            'message' => 'User created successfully.',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        if ((int) $request->user()?->getKey() === (int) $user->getKey()) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot change your own role.',
                'errors'  => [
                    'user' => ['Self role updates are not allowed.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldRole = $user->role;
        $newRole = $request->validated('role');

        $user->update(['role' => $newRole]);

        $actor = $request->user();

        if ($actor instanceof User) {
            $this->adminNotificationService->notifyUserRoleChanged($user, $actor, $oldRole, $newRole);
        }

        return response()->json([
            'status'  => true,
            'message' => "Role updated to '{$user->role}' for {$user->name}.",
            'data'    => [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['password'] ?? null)) {
            unset($data['password']);
        }

        $updated = $this->userService->updateAdminUser($user, $data);

        return response()->json([
            'status'  => true,
            'message' => 'User updated successfully.',
            'data'    => [
                'id'    => $updated->id,
                'name'  => $updated->name,
                'email' => $updated->email,
                'role'  => $updated->role,
            ],
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ((int) $request->user()?->getKey() === (int) $user->getKey()) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot delete your own account.',
                'errors'  => [
                    'user' => ['Self-deletion is not allowed.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->isLastSuperAdmin($user)) {
            return response()->json([
                'status'  => false,
                'message' => 'The last super admin account cannot be deleted.',
                'errors'  => [
                    'user' => ['At least one super admin account must remain.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = $user->name;

        $this->userService->delete($user);

        return response()->json([
            'status'  => true,
            'message' => "User '{$name}' deleted successfully.",
            'data'    => null,
        ]);
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return $user->role === 'super_admin'
            && User::query()->where('role', 'super_admin')->count() <= 1;
    }
}
