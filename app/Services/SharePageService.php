<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\HopeEvent;
use App\Models\Post;
use App\Support\ShareCard;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SharePageService
{
    public function post(int $id): ShareCard
    {
        $post = Post::query()->with(['category', 'author'])->findOrFail($id);
        if ($post->status !== 'published') {
            throw new NotFoundHttpException('Post not found.');
        }

        $image = $this->absoluteUrl($post->media_url)
            ?? $this->youtubeThumb($post->youtube_url)
            ?? $this->fallbackImage();

        $meta = collect([
            $post->author?->name,
            $post->category?->name,
            optional($post->publish_date)?->timezone('Asia/Manila')->format('d M Y'),
        ])->filter()->implode(' · ');

        return new ShareCard(
            type: 'post',
            id: $post->id,
            typeLabel: 'Post',
            title: $post->title,
            description: $this->excerpt($post->body),
            imageUrl: $image,
            meta: $meta !== '' ? $meta : null,
            canonicalUrl: $this->canonical('posts', $post->id),
            appPath: 'posts/'.$post->id,
        );
    }

    public function event(int $id): ShareCard
    {
        $event = HopeEvent::query()->findOrFail($id);
        if (! $event->is_active) {
            throw new NotFoundHttpException('Event not found.');
        }

        $place = $event->is_online
            ? ($event->online_label ?: 'Online')
            : ($event->venue ?: null);

        $meta = collect([
            $this->formatEventDates($event),
            $place,
        ])->filter()->implode(' · ');

        return new ShareCard(
            type: 'event',
            id: $event->id,
            typeLabel: 'Event',
            title: $event->title,
            description: $this->excerpt($event->about_text ?: $event->details_text),
            imageUrl: $this->absoluteUrl($event->coverUrl()) ?? $this->fallbackImage(),
            meta: $meta !== '' ? $meta : null,
            canonicalUrl: $this->canonical('events', $event->id),
            appPath: 'events/'.$event->id,
        );
    }

    public function contest(int $id): ShareCard
    {
        $contest = Contest::query()->findOrFail($id);
        if (! $contest->is_active || ! in_array($contest->status, ['open', 'closed', 'completed'], true)) {
            throw new NotFoundHttpException('Contest not found.');
        }

        $meta = collect([
            ucfirst((string) $contest->category),
            $contest->theme,
            $contest->contest_year ? (string) $contest->contest_year : null,
        ])->filter()->implode(' · ');

        return new ShareCard(
            type: 'contest',
            id: $contest->id,
            typeLabel: 'Contest',
            title: $contest->title,
            description: $this->excerpt($contest->description ?: $contest->theme),
            imageUrl: $this->absoluteUrl($contest->cover_image_url) ?? $this->fallbackImage(),
            meta: $meta !== '' ? $meta : null,
            canonicalUrl: $this->canonical('contests', $contest->id),
            appPath: 'contests/'.$contest->id,
        );
    }

    public function apkPath(): ?string
    {
        foreach ([
            public_path('download/dape-ma.apk'),
            storage_path('app/public/downloads/dape-ma.apk'),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function excerpt(?string $html): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($text === '') {
            return 'Open this in the DAPE-MA app.';
        }

        if (mb_strlen($text) <= 160) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 157)).'…';
    }

    private function canonical(string $type, int $id): string
    {
        return rtrim((string) config('app.url'), '/').'/'.$type.'/'.$id;
    }

    private function fallbackImage(): string
    {
        return rtrim((string) config('app.url'), '/').'/ddb.svg';
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            return rtrim((string) config('app.url'), '/').$trimmed;
        }

        $stored = Storage::disk('public')->url($trimmed);
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($stored, '/');
    }

    private function youtubeThumb(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (! preg_match('/(?:youtu\.be\/|v=)([A-Za-z0-9_-]{11})/', $url, $matches)) {
            return null;
        }

        return 'https://img.youtube.com/vi/'.$matches[1].'/hqdefault.jpg';
    }

    private function formatEventDates(HopeEvent $event): ?string
    {
        $start = $event->start_date;
        $end = $event->end_date;
        if (! $start) {
            return null;
        }

        if (! $end || $end->isSameDay($start)) {
            return $start->format('d M Y');
        }

        if ($start->isSameMonth($end) && $start->isSameYear($end)) {
            return $start->format('d').'–'.$end->format('d M Y');
        }

        return $start->format('d M Y').' – '.$end->format('d M Y');
    }
}
