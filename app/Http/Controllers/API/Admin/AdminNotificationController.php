<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly AdminNotificationService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->integer('per_page', 10);
        $unreadOnly = (bool) $request->boolean('unread_only', false);

        return response()->json([
            'status' => true,
            'data' => $this->service->listForUser($user, $perPage, $unreadOnly),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'data' => $this->service->unreadSummary($user),
        ]);
    }

    public function markAsRead(Request $request, AdminNotification $notification): JsonResponse
    {
        $this->service->markAsRead($notification, $request->user());

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

