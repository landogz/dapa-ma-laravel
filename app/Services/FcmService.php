<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM / APNs push notification dispatcher.
 *
 * Wire kreait/firebase-php here when the Firebase service account JSON is
 * available. The stub below uses the FCM v1 HTTP API directly so the rest
 * of the system can be tested without credentials — replace the body of
 * sendToTopic() and sendToToken() when ready.
 */
class FcmService
{
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        $serverKey = config('services.fcm.server_key');

        if (empty($serverKey)) {
            Log::warning('[FCM] server_key not configured — notification not dispatched.', compact('topic', 'title'));

            return false;
        }

        $payload = [
            'to'           => "/topics/{$topic}",
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => $data,
        ];

        $response = Http::withHeaders([
            'Authorization' => "key={$serverKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        if ($response->failed()) {
            Log::error('[FCM] Dispatch failed.', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $serverKey = config('services.fcm.server_key');

        if (empty($serverKey)) {
            Log::warning('[FCM] server_key not configured — token notification not dispatched.');

            return false;
        }

        $payload = [
            'to'           => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => $data,
        ];

        $response = Http::withHeaders([
            'Authorization' => "key={$serverKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        if ($response->failed()) {
            Log::error('[FCM] Token dispatch failed.', ['status' => $response->status()]);

            return false;
        }

        return true;
    }
}
