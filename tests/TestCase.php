<?php

namespace Tests;

use App\Support\Auth\AdminGateCookie;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withAdminGate(): static
    {
        return $this->withUnencryptedCookie(AdminGateCookie::NAME, '1');
    }
}
