<?php

namespace Tests\Unit;

use App\Services\ShareOgImageService;
use App\Support\ShareCard;
use Tests\TestCase;

class ShareOgImageServiceTest extends TestCase
{
    public function test_renders_facebook_sized_jpeg_from_event_banner(): void
    {
        $service = new ShareOgImageService();
        $dest = storage_path('app/og/test-event-banner.jpg');

        $service->renderToFile('/assets/hope/hope_banner_caravan.png', $dest);

        $this->assertFileExists($dest);
        $info = getimagesize($dest);
        $this->assertIsArray($info);
        $this->assertSame(1200, $info[0]);
        $this->assertSame(630, $info[1]);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_falls_back_to_logo_when_source_is_missing(): void
    {
        $service = new ShareOgImageService();
        $dest = storage_path('app/og/test-logo-fallback.jpg');

        $service->renderToFile('/storage/missing-featured.png', $dest);

        $this->assertFileExists($dest);
        $info = getimagesize($dest);
        $this->assertIsArray($info);
        $this->assertSame(1200, $info[0]);
        $this->assertSame(630, $info[1]);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertGreaterThan(8_000, filesize($dest));
    }

    public function test_cache_path_uses_share_uuid_instead_of_undefined_id(): void
    {
        $service = new ShareOgImageService();
        $uuid = 'c73d837d-bc9a-4699-b6ce-2bbb69e81132';
        $card = new ShareCard(
            type: 'post',
            id: 2,
            uuid: $uuid,
            typeLabel: 'Post',
            title: 'Sample Post 2 for DAPE-MA',
            description: 'Open this in the DAPE-MA app.',
            imageUrl: 'https://example.test/og.jpg',
            imageSrc: ShareOgImageService::LOGO_SRC,
            meta: null,
            canonicalUrl: 'https://example.test/posts/'.$uuid,
            appPath: 'posts/2',
        );

        $path = $service->pathFor('posts', $card);

        $this->assertFileExists($path);
        $this->assertStringContainsString($uuid, $path);
        $info = getimagesize($path);
        $this->assertIsArray($info);
        $this->assertSame('image/jpeg', $info['mime']);
    }
}
