<?php

namespace Tests\Unit\Support\Html;

use App\Support\Html\SafeHtml;
use Tests\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_it_strips_scripts_and_event_handlers(): void
    {
        $dirty = '<p onclick="alert(1)">Hello</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>';
        $clean = SafeHtml::sanitize($dirty);

        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('script', strtolower((string) $clean));
        $this->assertStringNotContainsString('onclick', (string) $clean);
        $this->assertStringNotContainsString('javascript:', (string) $clean);
    }
}
