<?php

namespace App\Http\Controllers;

use App\Services\SharePageService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharePageController extends Controller
{
    public function __construct(
        private readonly SharePageService $sharePageService,
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
