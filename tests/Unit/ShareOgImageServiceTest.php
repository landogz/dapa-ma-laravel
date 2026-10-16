<?php

namespace Tests\Unit;

use App\Services\ShareOgImageService;
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
}
