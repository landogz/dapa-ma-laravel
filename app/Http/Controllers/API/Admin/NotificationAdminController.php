<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendNotificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class NotificationAdminController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'topic' => ['sometimes', 'nullable', 'string', 'in:all,android,ios'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $filters = [
            'topic' => $validated['topic'] ?? null,
            'search' => $validated['search'] ?? null,
        ];

        $paginator = $this->notificationService->list($perPage, $filters);

        return response()->json([
            'status' => true,
            'message' => 'Notifications fetched successfully.',
            'data' => $paginator->through(
                fn ($notification) => $this->notificationService->format($notification),
            ),
        ]);
    }

    public function audienceCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => ['sometimes', 'nullable', 'string', 'in:all,android,ios'],
        ]);

        $topic = $validated['topic'] ?? 'all';

        return response()->json([
            'status' => true,
            'message' => 'Audience count fetched successfully.',
            'data' => $this->notificationService->audienceCount($topic),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $notification = $this->notificationService->show($id);

        return response()->json([
            'status' => true,
            'message' => 'Notification fetched successfully.',
            'data' => $this->notificationService->format($notification),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->notificationService->delete($id);

        return response()->json([
            'status' => true,
            'message' => 'Campaign history deleted. User inboxes were not changed.',
            'data' => null,
        ]);
    }

    public function send(SendNotificationRequest $request): JsonResponse
    {
        try {
            $result = $this->notificationService->send($request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], 422);
        }

        return response()->json([
            'status' => $result['ok'],
            'message' => $result['message'],
            'data' => [
                'notification' => $this->notificationService->format($result['notification']),
                'fcm_configured' => $result['fcm_configured'],
                'recipient_count' => $result['recipient_count'],
                'token_count' => $result['token_count'],
                'sent_count' => $result['sent_count'],
                'failed_count' => $result['failed_count'],
                'inbox_count' => $result['inbox_count'],
            ],
        ], $result['http_status']);
    }
}
