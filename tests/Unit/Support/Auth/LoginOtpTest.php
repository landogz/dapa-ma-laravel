<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\LoginOtp;
use Tests\TestCase;

class LoginOtpTest extends TestCase
{
    public function test_local_environment_skips_login_otp(): void
    {
        $this->app['env'] = 'local';

        $this->assertFalse(LoginOtp::required());
    }

    public function test_non_local_environments_require_login_otp(): void
    {
        $this->app['env'] = 'testing';
        $this->assertTrue(LoginOtp::required());

        $this->app['env'] = 'production';
        $this->assertTrue(LoginOtp::required());
    }
}
