<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM push notification dispatcher (legacy HTTP API).
 */
class FcmService
{
    public function isConfigured(): bool
    {
        $key = trim((string) config('services.fcm.server_key'));

        // Must be the legacy Cloud Messaging server key (usually starts with "AAAA").
        // Service-account emails / JSON client_email values are not valid here.
        if ($key === '' || str_contains($key, '@') || str_starts_with($key, '{')) {
            return false;
        }

        return true;
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('[FCM] server_key not configured — notification not dispatched.', compact('topic', 'title'));

            return false;
        }

        return $this->dispatch([
            'to' => "/topics/{$topic}",
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $this->stringifyData($data),
            'priority' => 'high',
        ], 'topic');
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('[FCM] server_key not configured — token notification not dispatched.');

            return false;
        }

        if ($token === '') {
            return false;
        }

        return $this->dispatch([
            'to' => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $this->stringifyData($data),
            'priority' => 'high',
        ], 'token');
    }

    /**
     * @param  list<string>  $tokens
     * @return array{sent:int, failed:int}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $sent = 0;
        $failed = 0;

        foreach (array_values(array_unique(array_filter($tokens))) as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(array $payload, string $channel): bool
    {
        $serverKey = config('services.fcm.server_key');

        $response = Http::withHeaders([
            'Authorization' => "key={$serverKey}",
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        if ($response->failed()) {
            Log::error('[FCM] Dispatch failed.', [
                'channel' => $channel,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();
        if (is_array($json) && isset($json['failure']) && (int) $json['failure'] > 0 && (int) ($json['success'] ?? 0) === 0) {
            Log::error('[FCM] Dispatch reported failure.', ['channel' => $channel, 'body' => $json]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $out;
    }
}
