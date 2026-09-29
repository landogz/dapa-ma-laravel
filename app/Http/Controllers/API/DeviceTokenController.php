<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\UpdateFcmTokenRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function update(UpdateFcmTokenRequest $request): JsonResponse
    {
        $user = $this->notificationService->updateDeviceToken(
            $request->user(),
            $request->validated('fcm_token'),
            $request->validated('platform'),
        );

        return response()->json([
            'status' => true,
            'message' => 'Device token registered.',
            'data' => [
                'fcm_platform' => $user->fcm_platform,
                'has_token' => filled($user->fcm_token),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->notificationService->clearDeviceToken($request->user());

        return response()->json([
            'status' => true,
            'message' => 'Device token cleared.',
            'data' => null,
        ]);
    }
}
