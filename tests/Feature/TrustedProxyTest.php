<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/url', fn () => url('/login'));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_https_is_used_behind_a_proxy_on_a_private_network(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->get('/_test/url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'signup.example.com', 'X-Forwarded-Port' => '443'])
            ->assertContent('https://signup.example.com/login');
    }

    public function test_forwarded_headers_from_the_internet_are_ignored(): void
    {
        $url = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->get('/_test/url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])
            ->getContent();

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringNotContainsString('evil.example.com', $url);
    }
}
