<?php

namespace App\Http\Controllers;

use App\Services\ShareOgImageService;
use App\Services\SharePageService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharePageController extends Controller
{
    public function __construct(
        private readonly SharePageService $sharePageService,
        private readonly ShareOgImageService $shareOgImageService,
    ) {
    }

    public function post(int $id)
    {
        return view('share.show', [
            'card' => $this->sharePageService->post($id),
        ]);
    }

    public function event(int $id)
    {
        return view('share.show', [
            'card' => $this->sharePageService->event($id),
        ]);
    }

    public function contest(int $id)
    {
        return view('share.show', [
            'card' => $this->sharePageService->contest($id),
        ]);
    }

    public function ogImage(string $type, int $id): BinaryFileResponse
    {
        $path = $this->shareOgImageService->pathFor($type, $id);

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
}
