<?php

namespace App\Http\Controllers;

use App\Services\ShareOgImageService;
use App\Services\SharePageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharePageController extends Controller
{
    public function __construct(
        private readonly SharePageService $sharePageService,
        private readonly ShareOgImageService $shareOgImageService,
    ) {
    }

    public function post(string $key)
    {
        return $this->show('posts', $this->sharePageService->post($key), $key);
    }

    public function event(string $key)
    {
        return $this->show('events', $this->sharePageService->event($key), $key);
    }

    public function contest(string $key)
    {
        return $this->show('contests', $this->sharePageService->contest($key), $key);
    }

    public function ogImage(string $type, string $key): BinaryFileResponse|RedirectResponse
    {
        $card = match ($type) {
            'posts' => $this->sharePageService->post($key),
            'events' => $this->sharePageService->event($key),
            'contests' => $this->sharePageService->contest($key),
            default => abort(404),
        };

        if (! Str::isUuid($key) && $card->uuid !== '') {
            return redirect()->route('share.og-image', [
                'type' => $type,
                'key' => $card->uuid,
            ], 301);
        }

        $path = $this->shareOgImageService->pathFor($type, $card);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }

    public function apk(): BinaryFileResponse|Response
    {
        $path = $this->sharePageService->apkPath();
        if (! $path) {
            abort(404, 'Android APK is not available yet.');
        }

        return response()->download(
            $path,
            'dape-ma.apk',
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    private function show(string $routeType, $card, string $key)
    {
        if (! Str::isUuid($key) && $card->uuid !== '') {
            return redirect()->route('share.'.$routeType, ['key' => $card->uuid], 301);
        }

        return view('share.show', [
            'card' => $card,
        ]);
    }
}
