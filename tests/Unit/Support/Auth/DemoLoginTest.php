<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\DemoLogin;
use Illuminate\Http\Request;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    public function test_localhost_shows_demo_accounts_in_local(): void
    {
        $request = Request::create('http://127.0.0.1:8000/admin/login');

        $this->assertNotEmpty(DemoLogin::accountsFor($request));
        $this->assertTrue(DemoLogin::shouldShow($request));
    }

    public function test_production_hides_demo_accounts_when_flag_is_off(): void
    {
        $this->app['env'] = 'production';
        config(['dape.show_demo_login' => false]);
        $request = Request::create('https://dapemade.ddb.gov.ph/admin/login');

        $this->assertFalse(DemoLogin::shouldShow($request));
        $this->assertSame([], DemoLogin::accountsFor($request));
    }

    public function test_production_shows_demo_accounts_when_flag_is_on(): void
    {
        $this->app['env'] = 'production';
        config(['dape.show_demo_login' => true]);
        $request = Request::create('https://dapemade.ddb.gov.ph/admin/login');

        $this->assertTrue(DemoLogin::shouldShow($request));
        $this->assertNotEmpty(DemoLogin::accountsFor($request));
    }
}
