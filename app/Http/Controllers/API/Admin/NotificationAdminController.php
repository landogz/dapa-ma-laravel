<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendNotificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationAdminController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);

        return response()->json([
            'status' => true,
            'data'   => $this->notificationService->list($perPage),
        ]);
    }

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $notification = $this->notificationService->send($request->validated(), $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Push notification dispatched.',
            'data'    => $notification,
        ], 201);
    }
}
