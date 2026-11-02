<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EditorMediaAdminController extends Controller
{
    /**
     * Upload an image or video for insertion into Body / rich text (CKEditor).
     * Accepts CKEditor Simple Upload field `upload`, or `file` from Add Media.
     */
    public function store(Request $request): JsonResponse
    {
        $file = $request->file('upload') ?? $request->file('file');

        if (! $file) {
            throw ValidationException::withMessages([
                'upload' => 'Choose an image or video to upload.',
            ]);
        }

        $request->files->set('upload', $file);

        $validated = $request->validate([
            'upload' => [
                'required',
                'file',
                'max:51200',
                'mimes:jpg,jpeg,png,webp,gif,mp4,webm,ogg,mov',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg,video/quicktime',
            ],
        ]);

        $uploaded = $validated['upload'];
        $mime = (string) $uploaded->getMimeType();
        $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';
        $folder = $kind === 'video' ? 'editor-media/videos' : 'editor-media/images';
        $path = $uploaded->store($folder, 'public');
        $url = Storage::disk('public')->url($path);

        // CKEditor SimpleUploadAdapter expects `{ url: "..." }`.
        return response()->json([
            'url' => $url,
            'status' => true,
            'message' => $kind === 'video' ? 'Video uploaded.' : 'Image uploaded.',
            'data' => [
                'url' => $url,
                'kind' => $kind,
                'mime' => $mime,
                'name' => $uploaded->getClientOriginalName(),
            ],
        ]);
    }
}
