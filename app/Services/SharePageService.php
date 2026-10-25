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
    private const LOGO_PATH = 'email/ddb-seal.png';

    public function post(string $key): ShareCard
    {
        $post = Post::findByPublicKey($key)->load(['category', 'author']);
        if ($post->status !== 'published') {
            throw new NotFoundHttpException('Post not found.');
        }

        $image = $this->resolveImage(
            $this->absoluteUrl($post->media_url),
            self::LOGO_PATH,
        );

        $meta = collect([
            $post->author?->name,
            $post->category?->name,
            optional($post->publish_date)?->timezone('Asia/Manila')->format('d M Y'),
        ])->filter()->implode(' · ');

        return new ShareCard(
            type: 'post',
            id: $post->id,
            uuid: (string) $post->uuid,
            typeLabel: 'Post',
            title: $post->title,
            description: $this->excerpt($post->body),
            imageUrl: $this->ogImageUrl('posts', (string) $post->uuid),
            imageSrc: $image['src'],
            meta: $meta !== '' ? $meta : null,
            canonicalUrl: $this->canonical('posts', (string) $post->uuid),
            appPath: 'posts/'.$post->id,
            imageIsLogo: $image['is_logo'],
        );
    }

    public function event(string $key): ShareCard
    {
        $event = HopeEvent::findByPublicKey($key);
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

        $image = $this->resolveImage(
            $this->absoluteUrl($event->coverUrl()),
            self::LOGO_PATH,
        );

        return new ShareCard(
            type: 'event',
            id: $event->id,
            uuid: (string) $event->uuid,
            typeLabel: 'Event',
            title: $event->title,
            description: $this->excerpt($event->about_text ?: $event->details_text),
            imageUrl: $this->ogImageUrl('events', (string) $event->uuid),
            imageSrc: $image['src'],
            meta: $meta !== '' ? $meta : null,
            canonicalUrl: $this->canonical('events', (string) $event->uuid),
            appPath: 'events/'.$event->id,
            imageIsLogo: $image['is_logo'],
        );
    }

    public function contest(string $key): ShareCard
    {
        $contest = Contest::findByPublicKey($key);
        if (! $contest->is_active || ! in_array($contest->status, ['open', 'closed', 'completed'], true)) {
            throw new NotFoundHttpException('Contest not found.');
        }

        $meta = collect([
            ucfirst((string) $contest->category),
            $contest->theme,
            $contest->contest_year ? (string) $contest->contest_year : null,
        ])->filter()->implode(' · ');

        $image = $this->resolveImage(
            $this->absoluteUrl($contest->cover_image_url),
            self::LOGO_PATH,
        );

        return new ShareCard(
            type: 'contest',
            id: $contest->id,
            uuid: (string) $contest->uuid,
            typeLabel: 'Contest',
            title: $contest->title,
            description: $this->excerpt($contest->description ?: $contest->theme),
            imageUrl: $this->ogImageUrl('contests', (string) $contest->uuid),
            imageSrc: $image['src'],
            meta: $meta !== '' ? $meta : null,
            canonicalUrl: $this->canonical('contests', (string) $contest->uuid),
            appPath: 'contests/'.$contest->id,
            imageIsLogo: $image['is_logo'],
        );
    }

    public function apkPath(): ?string
    {
        return $this->downloadPath('dape-ma.apk');
    }

    public function apkDownloadUrl(): string
    {
        return $this->downloadUrl('dape-ma.apk', $this->apkPath());
    }

    public function ipaPath(): ?string
    {
        return $this->downloadPath('dape-ma-ios.bin')
            ?? $this->downloadPath('dape-ma.ipa');
    }

    public function ipaDownloadUrl(): string
    {
        return $this->downloadUrl('dape-ma.ipa', $this->ipaPath());
    }

    private function downloadPath(string $filename): ?string
    {
        foreach ([
            public_path('download/'.$filename),
            storage_path('app/public/downloads/'.$filename),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function downloadUrl(string $filename, ?string $path): string
    {
        $url = url('/download/'.$filename);

        return $path ? $url.'?v='.filemtime($path) : $url;
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

    private function canonical(string $type, string $uuid): string
    {
        return $this->origin().'/'.$type.'/'.$uuid;
    }

    private function ogImageUrl(string $type, string $uuid): string
    {
        return $this->origin().'/og-image/'.$type.'/'.$uuid.'.jpg?v='.ShareOgImageService::VERSION;
    }

    /**
     * @return array{url: string, src: string, is_logo: bool}
     */
    private function resolveImage(?string $uploaded, string $fallbackRelative): array
    {
        if ($uploaded) {
            return [
                'url' => $this->toAbsolute($uploaded),
                'src' => $this->toPageSrc($uploaded),
                'is_logo' => false,
            ];
        }

        return [
            'url' => $this->origin().'/'.$fallbackRelative,
            'src' => '/'.$fallbackRelative,
            'is_logo' => true,
        ];
    }

    private function origin(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        if ($configured !== '' && preg_match('#^https?://#i', $configured) === 1) {
            return $configured;
        }

        return rtrim(request()->getSchemeAndHttpHost(), '/');
    }

    private function toAbsolute(string $path): string
    {
        $trimmed = trim($path);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            return $this->origin().$trimmed;
        }

        return $this->origin().'/'.$trimmed;
    }

    private function toPageSrc(string $path): string
    {
        $trimmed = trim($path);
        $parsed = parse_url($trimmed);
        if (isset($parsed['scheme'], $parsed['path']) && in_array($parsed['scheme'], ['http', 'https'], true)) {
            return $parsed['path'].(isset($parsed['query']) ? '?'.$parsed['query'] : '');
        }

        return str_starts_with($trimmed, '/') ? $trimmed : '/'.$trimmed;
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
            return $this->origin().$trimmed;
        }

        $stored = Storage::disk('public')->url($trimmed);
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        return $this->origin().'/'.ltrim($stored, '/');
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
