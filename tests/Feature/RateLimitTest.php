<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    protected function tearDown(): void
    {
        RateLimiter::clear('search');
        RateLimiter::clear('login');

        parent::tearDown();
    }

    public function test_search_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get(route('ajax.siteSearch', ['query' => 'test']))
                ->assertSuccessful();
        }

        $this->get(route('ajax.siteSearch', ['query' => 'test']))
            ->assertStatus(429);
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
