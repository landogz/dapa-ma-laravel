<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly FcmService             $fcmService,
    ) {
    }

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return $this->notificationRepository->paginate($perPage);
    }

    public function send(array $data, User $sender): Notification
    {
        $topic = $data['topic'] ?? 'all';
        $postId = $data['post_id'] ?? null;
        $payloadData = array_filter([
            'post_id' => $postId ? (string) $postId : null,
        ]);

        $this->fcmService->sendToTopic(
            $topic,
            $data['title'],
            $data['body'],
            array_merge($data['data'] ?? [], $payloadData),
        );

        return $this->notificationRepository->create([
            'title'   => $data['title'],
            'body'    => $data['body'],
            'topic'   => $topic,
            'post_id' => $postId,
            'sent_by' => $sender->id,
            'sent_at' => Carbon::now(),
        ]);
    }
}
