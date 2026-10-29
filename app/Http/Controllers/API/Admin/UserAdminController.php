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
use Illuminate\Validation\ValidationException;
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
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $filters = [
            'audience' => $request->query('audience'),
            'role' => $request->query('role'),
            'q' => $request->query('q', $request->query('search')),
            'status' => $request->query('status'),
        ];

        $result = $this->userService->listPaginated($perPage, $filters);
        $paginator = $result['paginator'];

        return response()->json([
            'status'  => true,
            'message' => 'Users fetched.',
            'data'    => $paginator,
            'summary' => $result['summary'],
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
                'is_active' => (bool) $user->is_active,
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
                'is_active' => (bool) $updated->is_active,
            ],
        ]);
    }

    public function forcePassword(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $updated = $this->userService->forcePasswordChange($actor, $user);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }

        return response()->json([
            'status' => true,
            'message' => "{$updated->name} must change password on next login.",
            'data' => [
                'id' => $updated->id,
                'must_change_password' => true,
            ],
        ]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $updated = $this->userService->suspend($actor, $user);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        }

        return response()->json([
            'status' => true,
            'message' => "{$updated->name} has been suspended.",
            'data' => [
                'id' => $updated->id,
                'is_active' => false,
            ],
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $updated = $this->userService->activate($user);

        return response()->json([
            'status' => true,
            'message' => "{$updated->name} has been enabled.",
            'data' => [
                'id' => $updated->id,
                'is_active' => true,
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

        if ($this->userService->isLastActiveSuperAdmin($user)
            || ($user->role === 'super_admin' && $this->isLastSuperAdmin($user))) {
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

    private function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        $messages = $exception->errors();
        $first = collect($messages)->flatten()->first() ?? 'Unable to complete this action.';

        return response()->json([
            'status' => false,
            'message' => $first,
            'errors' => $messages,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
