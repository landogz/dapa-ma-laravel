<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function __construct(
        private readonly UserNotificationService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->integer('per_page', 20);
        $unreadOnly = (bool) $request->boolean('unread_only', false);

        return response()->json([
            'status' => true,
            'message' => 'Notifications fetched successfully.',
            'data' => $this->service->listForUser($user, $perPage, $unreadOnly),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->service->unreadSummary($request->user()),
        ]);
    }

    public function markAsRead(Request $request, UserNotification $userNotification): JsonResponse
    {
        $this->service->markAsRead($userNotification, $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->service->markAllAsRead($request->user());

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read.',
            'data' => ['updated' => $count],
        ]);
    }
}
