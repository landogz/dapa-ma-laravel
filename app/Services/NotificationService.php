<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Repositories\NotificationRepository;
use App\Repositories\UserNotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly UserNotificationRepository $userNotificationRepository,
        private readonly FcmService $fcmService,
    ) {
    }

    public function list(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->notificationRepository->paginate($perPage, $filters);
    }

    public function show(int $id): Notification
    {
        return $this->notificationRepository->findOrFail($id);
    }

    public function delete(int $id): void
    {
        $notification = $this->notificationRepository->findOrFail($id);
        $this->notificationRepository->delete($notification);
    }

    /**
     * @return array{topic: string, recipient_count: int, token_count: int}
     */
    public function audienceCount(string $topic = 'all'): array
    {
        $recipients = $this->resolveRecipients($topic);
        $tokenCount = $recipients
            ->pluck('fcm_token')
            ->filter(static fn ($token) => filled($token))
            ->unique()
            ->count();

        return [
            'topic' => $topic,
            'recipient_count' => $recipients->count(),
            'token_count' => $tokenCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     ok: bool,
     *     http_status: int,
     *     notification: Notification,
     *     fcm_configured: bool,
     *     recipient_count: int,
     *     token_count: int,
     *     sent_count: int,
     *     failed_count: int,
     *     inbox_count: int,
     *     message: string
     * }
     */
    public function send(array $data, User $sender): array
    {
        $topic = $data['topic'] ?? 'all';
        $postId = $data['post_id'] ?? null;
        $title = trim((string) ($data['title'] ?? ''));
        $plainBody = $this->stripHtml((string) ($data['body'] ?? ''));

        if ($title === '' || $plainBody === '') {
            throw new InvalidArgumentException('Title and body are required.');
        }

        $recipients = $this->resolveRecipients($topic);

        if ($recipients->isEmpty()) {
            throw new InvalidArgumentException(
                $this->emptyAudienceMessage($topic)
            );
        }

        $tokens = $recipients
            ->pluck('fcm_token')
            ->filter(static fn ($token) => filled($token))
            ->unique()
            ->values()
            ->all();

        $recipientCount = $recipients->count();

        $notification = $this->notificationRepository->create([
            'title' => $title,
            'body' => $plainBody,
            'topic' => $topic,
            'post_id' => $postId,
            'sent_by' => $sender->id,
            'sent_at' => Carbon::now(),
            'recipient_count' => $recipientCount,
            'inbox_count' => 0,
        ]);

        $payloadData = array_filter([
            'type' => 'admin_push',
            'post_id' => $postId ? (string) $postId : null,
            'topic' => (string) $topic,
            'notification_id' => (string) $notification->id,
        ]);

        $fcmConfigured = $this->fcmService->isConfigured();
        $sentCount = 0;
        $failedCount = 0;

        if ($fcmConfigured && $tokens !== []) {
            $result = $this->fcmService->sendToTokens(
                $tokens,
                $title,
                $plainBody,
                array_merge($data['data'] ?? [], $payloadData),
            );
            $sentCount = $result['sent'];
            $failedCount = $result['failed'];
        }

        $inboxCount = $this->userNotificationRepository->createForUsers($recipients, [
            'type' => 'admin_push',
            'title' => $title,
            'body' => $plainBody,
            'data' => array_filter([
                'post_id' => $postId,
                'topic' => $topic,
                'campaign' => true,
                'notification_id' => $notification->id,
            ]),
        ]);

        $notification = $this->notificationRepository->update($notification, [
            'recipient_count' => $recipientCount,
            'inbox_count' => $inboxCount,
        ]);

        $message = $this->buildResultMessage(
            fcmConfigured: $fcmConfigured,
            recipientCount: $recipientCount,
            tokenCount: count($tokens),
            sentCount: $sentCount,
            failedCount: $failedCount,
            inboxCount: $inboxCount,
        );

        $ok = $inboxCount > 0;
        $httpStatus = $ok ? 201 : 422;

        return [
            'ok' => $ok,
            'http_status' => $httpStatus,
            'notification' => $notification,
            'fcm_configured' => $fcmConfigured,
            'recipient_count' => $recipientCount,
            'token_count' => count($tokens),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'inbox_count' => $inboxCount,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function format(Notification $notification): array
    {
        $preview = $this->stripHtml((string) ($notification->body ?? ''));
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117).'...';
        }

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'preview' => $preview,
            'topic' => $notification->topic ?? 'all',
            'post_id' => $notification->post_id,
            'post' => $notification->post ? [
                'id' => $notification->post->id,
                'title' => $notification->post->title,
            ] : null,
            'sender' => $notification->sender ? [
                'id' => $notification->sender->id,
                'name' => $notification->sender->name,
                'email' => $notification->sender->email,
            ] : null,
            'sent_at' => $notification->sent_at?->toIso8601String(),
            'recipient_count' => (int) ($notification->recipient_count ?? 0),
            'inbox_count' => (int) ($notification->inbox_count ?? 0),
            'created_at' => $notification->created_at?->toIso8601String(),
            'updated_at' => $notification->updated_at?->toIso8601String(),
        ];
    }

    public function updateDeviceToken(User $user, string $token, string $platform): User
    {
        $user->forceFill([
            'fcm_token' => $token,
            'fcm_platform' => $platform,
        ])->save();

        return $user->fresh();
    }

    public function clearDeviceToken(User $user): void
    {
        $user->forceFill([
            'fcm_token' => null,
            'fcm_platform' => null,
        ])->save();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(string $topic): Collection
    {
        $query = User::query()
            ->where('role', 'app_user')
            ->orderBy('id');

        if (in_array($topic, ['android', 'ios'], true)) {
            $query->where('fcm_platform', $topic);
        }

        return $query->get(['id', 'fcm_token', 'fcm_platform']);
    }

    private function emptyAudienceMessage(string $topic): string
    {
        if ($topic === 'android') {
            return 'No Android app users found for this audience. Choose All users, or wait until devices register.';
        }

        if ($topic === 'ios') {
            return 'No iOS app users found for this audience. Choose All users, or wait until devices register.';
        }

        return 'No matching app users found for this audience.';
    }

    private function stripHtml(string $value): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function buildResultMessage(
        bool $fcmConfigured,
        int $recipientCount,
        int $tokenCount,
        int $sentCount,
        int $failedCount,
        int $inboxCount,
    ): string {
        if ($recipientCount === 0 || $inboxCount === 0) {
            return 'No matching app users found for this audience.';
        }

        if (! $fcmConfigured) {
            return "In-app notification sent to {$inboxCount} user(s).";
        }

        if ($tokenCount === 0) {
            return "In-app notification sent to {$inboxCount} user(s). Push skipped — no device tokens registered yet.";
        }

        if ($sentCount === 0) {
            return "In-app notification sent to {$inboxCount} user(s). Push could not be delivered to registered devices.";
        }

        $parts = ["In-app notification sent to {$inboxCount} user(s)", "push delivered to {$sentCount} device(s)"];
        if ($failedCount > 0) {
            $parts[] = "{$failedCount} push failed";
        }

        return implode('; ', $parts).'.';
    }
}
